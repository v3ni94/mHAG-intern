<?php

namespace App\Http\Controllers;

use App\Http\Requests\MasterData\IdentityDocumentRequest;
use App\Models\Entity;
use App\Models\IdentityDocument;
use App\Models\Reminder;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Identitätsdokumente einer Personenakte (Abschnitt 6 Masterprompt).
 * Ein Ablaufdatum erzeugt automatisch eine Wiedervorlage; die Dateiablage
 * (Vorder-/Rückseite) läuft über das Dokumentenmodul (document_links).
 */
class IdentityDocumentController extends Controller
{
    /** Vorlauf der Wiedervorlage vor Ablauf des Dokuments. */
    private const REMINDER_LEAD_WEEKS = 6;

    public function store(IdentityDocumentRequest $request, Entity $entity): RedirectResponse
    {
        $data = $this->applyVerification($request->validated());

        $document = $entity->identityDocuments()->create($data);
        $this->syncExpiryReminder($document, $entity);

        AuditService::log('entities.identity_document_created', $document, [], [
            'type' => $document->type->value,
            'document_number' => $document->document_number,
            'expires_on' => $document->expires_on?->toDateString(),
        ], ['entity_id' => $entity->id]);

        return redirect()->route('persons.show', [$entity, 'tab' => 'identitaet'])
            ->with('success', 'Das Identitätsdokument wurde gespeichert.');
    }

    public function update(IdentityDocumentRequest $request, Entity $entity, IdentityDocument $identityDocument): RedirectResponse
    {
        $data = $this->applyVerification($request->validated(), $identityDocument);
        $old = $identityDocument->only(array_keys($data));

        $identityDocument->update($data);
        $this->syncExpiryReminder($identityDocument, $entity);

        AuditService::log('entities.identity_document_updated', $identityDocument, $old, $data, ['entity_id' => $entity->id]);

        return redirect()->route('persons.show', [$entity, 'tab' => 'identitaet'])
            ->with('success', 'Das Identitätsdokument wurde aktualisiert.');
    }

    public function destroy(Request $request, Entity $entity, IdentityDocument $identityDocument): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);

        // Offene Wiedervorlage zum Dokument abbrechen.
        Reminder::query()
            ->where('remindable_type', $identityDocument->getMorphClass())
            ->where('remindable_id', $identityDocument->id)
            ->where('status', 'open')
            ->update(['status' => 'cancelled']);

        $old = $identityDocument->toArray();
        $identityDocument->delete();

        AuditService::log('entities.identity_document_deleted', null, $old, [], ['entity_id' => $entity->id]);

        return redirect()->route('persons.show', [$entity, 'tab' => 'identitaet'])
            ->with('success', 'Das Identitätsdokument wurde gelöscht.');
    }

    /** Prüfvermerk pflegen: Prüfdatum und Prüfer nur beim Wechsel auf "geprüft" setzen. */
    private function applyVerification(array $data, ?IdentityDocument $existing = null): array
    {
        $wasVerified = (bool) $existing?->verified;
        $isVerified = ! empty($data['verified']);

        if ($isVerified && ! $wasVerified) {
            $data['verified_at'] = now();
            $data['verified_by'] = auth()->id();
        } elseif (! $isVerified) {
            $data['verified_at'] = null;
            $data['verified_by'] = null;
        }

        return $data;
    }

    /**
     * Wiedervorlage zum Ablaufdatum anlegen bzw. fortschreiben (Abschnitt 6:
     * "Ablaufdaten müssen Wiedervorlagen auslösen können").
     */
    private function syncExpiryReminder(IdentityDocument $document, Entity $entity): void
    {
        $open = Reminder::query()
            ->where('remindable_type', $document->getMorphClass())
            ->where('remindable_id', $document->id)
            ->where('status', 'open')
            ->first();

        if (! $document->expires_on) {
            $open?->update(['status' => 'cancelled']);

            return;
        }

        $due = $document->expires_on->copy()->subWeeks(self::REMINDER_LEAD_WEEKS);
        if ($due->isBefore(today())) {
            $due = today();
        }

        $attributes = [
            'title' => 'Identitätsdokument läuft ab: '.$document->type->label().' ('.$entity->display_name.')',
            'description' => 'Das Dokument'
                .($document->document_number ? ' Nr. '.$document->document_number : '')
                .' läuft am '.format_date($document->expires_on).' ab. Bitte rechtzeitig erneuern lassen.',
            'due_date' => $due->toDateString(),
            'priority' => 'normal',
            'status' => 'open',
        ];

        if ($open) {
            $open->update($attributes);

            return;
        }

        Reminder::create($attributes + [
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
            'remindable_type' => $document->getMorphClass(),
            'remindable_id' => $document->id,
        ]);
    }
}
