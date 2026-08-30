<?php

namespace App\Services\Signature;

use App\Enums\ResolutionStatus;
use App\Enums\ShareTransactionStatus;
use App\Enums\SignatureParticipantStatus;
use App\Enums\SignatureRequestStatus;
use App\Models\Contract;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Entity;
use App\Models\Resolution;
use App\Models\ShareholderListSnapshot;
use App\Models\ShareTransaction;
use App\Models\SignatureRequest;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Manueller Signaturprozess (vollständig implementiert, Abschnitte 99 bis 102):
 * Das Dokument wird außerhalb des Systems unterschrieben (z. B. auf Papier
 * oder per separatem Tool). Die Status der Unterzeichner werden im Intranet
 * manuell gepflegt; die signierte Fassung wird als PDF hochgeladen und
 * schließt den Vorgang ab.
 */
class ManualSignatureAdapter implements SignatureServiceInterface
{
    public function create(Model $subject, Document $pdf, array $participants): SignatureRequest
    {
        return DB::transaction(function () use ($subject, $pdf, $participants) {
            $request = SignatureRequest::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'provider' => 'manual',
                'status' => SignatureRequestStatus::Draft->value,
                // Ausgangs-PDF; wird bei Abschluss durch die signierte Fassung ersetzt.
                'document_id' => $pdf->id,
                'created_by' => auth()->id(),
            ]);

            foreach ($participants as $participant) {
                if (empty($participant['entity_id'])) {
                    continue;
                }

                $email = $participant['email'] ?? null;
                if (! $email) {
                    $entity = Entity::with('contactDetails')->find($participant['entity_id']);
                    $email = $entity?->primaryEmail();
                }

                $request->participants()->create([
                    'entity_id' => $participant['entity_id'],
                    'role' => $participant['role'] ?? null,
                    'email' => $email,
                    'status' => SignatureParticipantStatus::NotSent->value,
                ]);
            }

            AuditService::log(
                'signatures.created',
                $request,
                [],
                ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'document_id' => $pdf->id],
            );

            return $request->load('participants');
        });
    }

    public function send(SignatureRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $oldStatus = $request->status?->value;
            $request->status = SignatureRequestStatus::Sent;
            $request->save();

            $request->participants()
                ->where('status', SignatureParticipantStatus::NotSent->value)
                ->get()
                ->each(function ($participant) {
                    $participant->update([
                        'status' => SignatureParticipantStatus::Sent->value,
                        'status_changed_at' => Carbon::now(),
                    ]);
                });

            AuditService::log(
                'signatures.sent',
                $request,
                ['status' => $oldStatus],
                ['status' => SignatureRequestStatus::Sent->value],
            );
        });
    }

    /**
     * Beim manuellen Adapter gibt es keinen externen Anbieter; der
     * Gesamtstatus wird aus den manuell gepflegten Teilnehmerstatus abgeleitet.
     * "Abgeschlossen" wird ausschließlich über attachSignedDocument gesetzt.
     */
    public function syncStatus(SignatureRequest $request): void
    {
        if (in_array($request->status, [SignatureRequestStatus::Completed, SignatureRequestStatus::Draft], true)) {
            return;
        }

        $statuses = $request->participants()->pluck('status')->map(
            fn ($s) => $s instanceof SignatureParticipantStatus ? $s : SignatureParticipantStatus::from((string) $s),
        );

        $new = $request->status;
        if ($statuses->contains(SignatureParticipantStatus::Error)) {
            $new = SignatureRequestStatus::Error;
        } elseif ($statuses->contains(SignatureParticipantStatus::Declined)) {
            $new = SignatureRequestStatus::Declined;
        } elseif ($statuses->isNotEmpty() && $statuses->every(fn ($s) => $s === SignatureParticipantStatus::Expired)) {
            $new = SignatureRequestStatus::Expired;
        } elseif ($statuses->contains(fn ($s) => in_array($s, [SignatureParticipantStatus::Opened, SignatureParticipantStatus::Signed], true))) {
            $new = SignatureRequestStatus::InProgress;
        }

        if ($new !== $request->status) {
            $old = $request->status?->value;
            $request->status = $new;
            $request->save();

            AuditService::log('signatures.status-synced', $request, ['status' => $old], ['status' => $new->value]);
        }
    }

    public function attachSignedDocument(SignatureRequest $request, Document $signed): void
    {
        DB::transaction(function () use ($request, $signed) {
            $oldStatus = $request->status?->value;
            $request->document_id = $signed->id;
            $request->status = SignatureRequestStatus::Completed;
            $request->save();

            $subject = $request->subject()->first();
            if ($subject) {
                $this->advanceSubjectStatus($subject);

                DocumentLink::firstOrCreate([
                    'document_id' => $signed->id,
                    'linkable_type' => $subject->getMorphClass(),
                    'linkable_id' => $subject->getKey(),
                ]);
            }

            AuditService::log(
                'signatures.completed',
                $request,
                ['status' => $oldStatus],
                ['status' => SignatureRequestStatus::Completed->value, 'document_id' => $signed->id, 'sha256' => $signed->sha256],
            );
        });
    }

    /** Vorgangsstatus nach Abschluss der Signatur fortschreiben (Abschnitt 100). */
    private function advanceSubjectStatus(Model $subject): void
    {
        if ($subject instanceof Resolution) {
            if (! in_array($subject->status, [ResolutionStatus::Signed, ResolutionStatus::Completed, ResolutionStatus::Archived], true)) {
                $old = $subject->status?->value;
                $subject->status = ResolutionStatus::Signed;
                $subject->save();
                AuditService::log('resolutions.signed', $subject, ['status' => $old], ['status' => ResolutionStatus::Signed->value]);
            }

            return;
        }

        if ($subject instanceof Contract) {
            if (! in_array($subject->status, ['signed', 'cancelled'], true)) {
                $old = $subject->status;
                $subject->status = 'signed';
                $subject->save();
                AuditService::log('contracts.signed', $subject, ['status' => $old], ['status' => 'signed']);
            }

            return;
        }

        if ($subject instanceof ShareTransaction) {
            // Bereits wirksame oder stornierte Bewegungen nicht zurückstufen.
            if (! in_array($subject->status, [
                ShareTransactionStatus::Signed,
                ShareTransactionStatus::Resolved,
                ShareTransactionStatus::Effective,
                ShareTransactionStatus::Cancelled,
            ], true)) {
                $old = $subject->status?->value;
                $subject->status = ShareTransactionStatus::Signed;
                $subject->save();
                AuditService::log('share-transactions.signed', $subject, ['status' => $old], ['status' => ShareTransactionStatus::Signed->value]);
            }

            return;
        }

        if ($subject instanceof ShareholderListSnapshot) {
            $old = $subject->signature_status;
            $subject->signature_status = 'signed';
            $subject->save();
            AuditService::log('shareholders.list-signed', $subject, ['signature_status' => $old], ['signature_status' => 'signed']);
        }
    }
}
