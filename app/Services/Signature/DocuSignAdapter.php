<?php

namespace App\Services\Signature;

use App\Enums\SignatureParticipantStatus;
use App\Enums\SignatureRequestStatus;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Services\AuditService;
use App\Services\Signature\DocuSign\DocuSignClient;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anbindung an DocuSign eSignature (Abschnitte 99 bis 102).
 *
 * Ablauf:
 * 1. create()  legt die Anfrage im Intranet an, noch ohne Umschlag bei
 *              DocuSign. Nichts wird versendet, solange nicht ausdrücklich
 *              versendet wird.
 * 2. send()    erzeugt den Umschlag mit dem PDF und den Unterzeichnern und
 *              versendet ihn. Die Umschlagkennung wird als external_id
 *              gespeichert.
 * 3. syncStatus() fragt den Umschlag ab und schreibt die Status der
 *              Unterzeichner und der Anfrage fort. Ist der Umschlag
 *              abgeschlossen, wird die unterschriebene Gesamtfassung geholt,
 *              als Dokument abgelegt und der Vorgang abgeschlossen.
 *
 * Die Fortschreibung des zugrunde liegenden Vorgangs (Beschluss, Vertrag,
 * Aktienbewegung, Aktionärsliste) und das Verknüpfen des signierten PDF
 * übernimmt die geerbte Logik des manuellen Adapters; sie ist für alle
 * Signaturwege dieselbe und darf nicht zweimal existieren.
 *
 * Fachliche Zurückhaltung: Das System bewertet nicht, ob eine Unterschrift
 * formwirksam ist. Es hält fest, was DocuSign zurückmeldet.
 */
class DocuSignAdapter extends ManualSignatureAdapter
{
    public function __construct(
        private readonly DocuSignClient $client,
        private readonly DocumentStorageInterface $storage,
    ) {}

    /** Zuordnung der DocuSign-Empfängerstatus auf die eigenen Status. */
    private const RECIPIENT_STATUS = [
        'created' => SignatureParticipantStatus::NotSent,
        'sent' => SignatureParticipantStatus::Sent,
        'delivered' => SignatureParticipantStatus::Opened,
        'signed' => SignatureParticipantStatus::Signed,
        'completed' => SignatureParticipantStatus::Signed,
        'declined' => SignatureParticipantStatus::Declined,
        'autoresponded' => SignatureParticipantStatus::Error,
    ];

    /** Zuordnung der DocuSign-Umschlagstatus auf die eigenen Status. */
    private const ENVELOPE_STATUS = [
        'created' => SignatureRequestStatus::Draft,
        'sent' => SignatureRequestStatus::Sent,
        'delivered' => SignatureRequestStatus::InProgress,
        'signed' => SignatureRequestStatus::InProgress,
        'completed' => SignatureRequestStatus::Completed,
        'declined' => SignatureRequestStatus::Declined,
        'voided' => SignatureRequestStatus::Expired,
    ];

    public function create(Model $subject, Document $pdf, array $participants): SignatureRequest
    {
        $this->guardConfigured();

        $request = parent::create($subject, $pdf, $participants);
        $request->update(['provider' => 'docusign']);

        return $request->fresh('participants');
    }

    /**
     * Umschlag bei DocuSign erzeugen und versenden. Ohne E-Mail-Adresse
     * eines Unterzeichners ist kein Versand möglich; das wird benannt statt
     * stillschweigend übergangen.
     */
    public function send(SignatureRequest $request): void
    {
        $this->guardConfigured();

        $request->loadMissing('participants.entity');
        $pdf = $request->document;
        if (! $pdf) {
            throw new RuntimeException('Zu dieser Anfrage ist kein PDF hinterlegt; es kann nichts versendet werden.');
        }

        $ohneAdresse = $request->participants->filter(fn ($p) => trim((string) $p->email) === '');
        if ($ohneAdresse->isNotEmpty()) {
            throw new RuntimeException(
                'Für '.$ohneAdresse->count().' Unterzeichner ist keine E-Mail-Adresse hinterlegt. '
                .'DocuSign versendet ausschließlich per E-Mail; bitte die Adressen in der Akte ergänzen.',
            );
        }
        if ($request->participants->isEmpty()) {
            throw new RuntimeException('Es ist kein Unterzeichner erfasst.');
        }

        $inhalt = $this->storage->retrieve($pdf);
        $anker = (string) config('docusign.anchor_string');

        $signer = [];
        $nummer = 0;
        foreach ($request->participants as $participant) {
            $nummer++;
            $signer[] = [
                'email' => $participant->email,
                'name' => $participant->entity?->display_name ?: ('Unterzeichner '.$nummer),
                'recipientId' => (string) $nummer,
                'routingOrder' => (string) $nummer,
                'roleName' => $participant->role,
                'tabs' => [
                    'signHereTabs' => [[
                        'anchorString' => $anker !== '' ? $anker : null,
                        'anchorUnits' => 'pixels',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '0',
                        // Rueckfall, wenn der Ankertext im PDF nicht vorkommt
                        'documentId' => '1',
                        'pageNumber' => '1',
                        'xPosition' => '80',
                        'yPosition' => (string) (620 + ($nummer - 1) * 60),
                    ]],
                ],
            ];
        }

        $antwort = $this->client->createEnvelope([
            'emailSubject' => (string) config('docusign.email_subject'),
            'status' => 'sent',
            'documents' => [[
                'documentBase64' => base64_encode($inhalt),
                'name' => $pdf->original_filename,
                'fileExtension' => 'pdf',
                'documentId' => '1',
            ]],
            'recipients' => ['signers' => $signer],
        ]);

        $envelopeId = (string) ($antwort['envelopeId'] ?? '');
        if ($envelopeId === '') {
            throw new RuntimeException('DocuSign hat keine Umschlagkennung zurückgegeben; der Versand ist nicht belegt.');
        }

        DB::transaction(function () use ($request, $envelopeId, $antwort) {
            $alt = $request->status?->value;
            $request->update([
                'external_id' => $envelopeId,
                'status' => SignatureRequestStatus::Sent->value,
            ]);

            $request->participants()->update([
                'status' => SignatureParticipantStatus::Sent->value,
                'status_changed_at' => Carbon::now(),
            ]);

            AuditService::log('signatures.sent', $request, ['status' => $alt], [
                'status' => SignatureRequestStatus::Sent->value,
                'provider' => 'docusign',
                'envelope_id' => $envelopeId,
                'envelope_status' => $antwort['status'] ?? null,
            ]);
        });
    }

    /**
     * Status beim Anbieter abfragen und fortschreiben. Ist der Umschlag
     * abgeschlossen, wird die unterschriebene Fassung übernommen.
     */
    public function syncStatus(SignatureRequest $request): void
    {
        if (! $request->external_id) {
            // Noch kein Umschlag: der abgeleitete Status genügt.
            parent::syncStatus($request);

            return;
        }

        $this->guardConfigured();

        $umschlag = $this->client->envelope((string) $request->external_id);
        $envelopeStatus = strtolower((string) ($umschlag['status'] ?? ''));

        $this->applyRecipients($request, (array) ($umschlag['recipients']['signers'] ?? []));

        $neu = self::ENVELOPE_STATUS[$envelopeStatus] ?? null;
        if ($neu === null) {
            return;
        }

        if ($neu === SignatureRequestStatus::Completed) {
            if ($request->status !== SignatureRequestStatus::Completed) {
                $this->fetchSignedDocument($request);
            }

            return;
        }

        if ($neu !== $request->status) {
            $alt = $request->status?->value;
            $request->update(['status' => $neu->value]);
            AuditService::log('signatures.status-synced', $request, ['status' => $alt], [
                'status' => $neu->value,
                'provider' => 'docusign',
                'envelope_status' => $envelopeStatus,
            ]);
        }
    }

    /**
     * Empfängerstatus übernehmen. Zuordnung über die E-Mail-Adresse, weil
     * DocuSign die eigenen Teilnehmerkennungen nicht kennt.
     *
     * @param  array<int, array<string, mixed>>  $signers
     */
    private function applyRecipients(SignatureRequest $request, array $signers): void
    {
        $request->loadMissing('participants');

        foreach ($signers as $signer) {
            $email = strtolower(trim((string) ($signer['email'] ?? '')));
            $status = strtolower((string) ($signer['status'] ?? ''));
            if ($email === '' || ! array_key_exists($status, self::RECIPIENT_STATUS)) {
                continue;
            }

            $participant = $request->participants
                ->first(fn ($p) => strtolower(trim((string) $p->email)) === $email);
            if (! $participant) {
                continue;
            }

            $neu = self::RECIPIENT_STATUS[$status];
            if ($participant->status !== $neu) {
                $participant->update([
                    'status' => $neu->value,
                    'status_changed_at' => Carbon::now(),
                ]);
            }
        }
    }

    /**
     * Unterschriebene Gesamtfassung holen, ablegen und den Vorgang
     * abschließen. Die Datei ersetzt das Ausgangs-PDF nicht, sie wird als
     * eigenes Dokument geführt.
     */
    private function fetchSignedDocument(SignatureRequest $request): void
    {
        $inhalt = $this->client->combinedDocument((string) $request->external_id);
        if (trim($inhalt) === '') {
            throw new RuntimeException('DocuSign hat keine unterschriebene Fassung geliefert.');
        }

        $name = 'unterschrieben-'.($request->document?->original_filename ?: 'dokument.pdf');
        if (! str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        $signiert = $this->storage->store($inhalt, 'signaturen/docusign', $name, [
            'doc_type' => 'signed_document',
            'description' => 'Unterschriebene Fassung aus DocuSign, Umschlag '.$request->external_id,
            'uploaded_by' => auth()->id(),
        ]);

        // Abschluss, Verknüpfung und Fortschreibung des Vorgangs wie beim
        // manuellen Weg: eine Stelle, ein Verhalten.
        $this->attachSignedDocument($request, $signiert);
    }

    private function guardConfigured(): void
    {
        $fehlt = $this->client->missingRequirements();
        if ($fehlt !== []) {
            throw new RuntimeException(
                'DocuSign ist nicht vollständig konfiguriert, es wird nichts versendet. '.implode(' ', $fehlt),
            );
        }
    }
}
