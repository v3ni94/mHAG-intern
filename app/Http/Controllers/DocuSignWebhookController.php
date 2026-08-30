<?php

namespace App\Http\Controllers;

use App\Models\SignatureRequest;
use App\Services\AuditService;
use App\Services\Signature\DocuSignAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Rückkanal von DocuSign (Connect, Abschnitt 102).
 *
 * Sicherheitsgrundsätze:
 *
 * 1. Die Nachricht wird nur angenommen, wenn die HMAC-Signatur mit dem
 *    hinterlegten Geheimnis übereinstimmt. Ohne Geheimnis in der
 *    Konfiguration wird gar nichts angenommen; ein offener Endpunkt wäre
 *    eine Einladung, Status von außen zu setzen.
 * 2. Dem Inhalt der Nachricht wird NICHT vertraut. Aus ihr wird
 *    ausschließlich die Umschlagkennung entnommen; der Status wird
 *    anschließend bei DocuSign abgefragt. Damit kann eine gefälschte oder
 *    veraltete Nachricht keinen falschen Status erzeugen.
 * 3. Es wird immer mit 200 geantwortet, sofern die Signatur stimmt, damit
 *    DocuSign die Nachricht nicht endlos wiederholt. Fehler werden
 *    protokolliert.
 */
class DocuSignWebhookController extends Controller
{
    /** Kopfzeilen, in denen DocuSign die HMAC-Signatur überträgt. */
    private const SIGNATURE_HEADERS = [
        'X-DocuSign-Signature-1',
        'X-DocuSign-Signature-2',
        'X-DocuSign-Signature-3',
    ];

    public function __invoke(Request $request, DocuSignAdapter $adapter): JsonResponse
    {
        $secret = (string) config('docusign.webhook_secret');
        if (trim($secret) === '') {
            Log::warning('DocuSign-Benachrichtigung abgewiesen: kein Geheimnis konfiguriert.');

            return response()->json(['status' => 'nicht konfiguriert'], 503);
        }

        $body = $request->getContent();
        if (! $this->signatureMatches($request, $body, $secret)) {
            Log::warning('DocuSign-Benachrichtigung abgewiesen: Signatur stimmt nicht.');

            return response()->json(['status' => 'abgewiesen'], 401);
        }

        $envelopeId = $this->envelopeId($request);
        if ($envelopeId === null) {
            return response()->json(['status' => 'ohne Umschlagkennung'], 202);
        }

        $signatureRequest = SignatureRequest::query()
            ->where('provider', 'docusign')
            ->where('external_id', $envelopeId)
            ->first();

        if (! $signatureRequest) {
            // Kein Fehler: der Umschlag kann in einem anderen System entstanden sein.
            return response()->json(['status' => 'unbekannter Umschlag'], 202);
        }

        try {
            // Bewusst nicht der Nachricht glauben, sondern beim Anbieter fragen.
            $adapter->syncStatus($signatureRequest);

            AuditService::log('signatures.webhook-received', $signatureRequest, [], [
                'envelope_id' => $envelopeId,
                'event' => (string) $request->input('event', ''),
                'status' => $signatureRequest->fresh()->status?->value,
            ]);
        } catch (\Throwable $e) {
            Log::error('DocuSign-Benachrichtigung konnte nicht verarbeitet werden: '.$e->getMessage());

            return response()->json(['status' => 'Verarbeitung fehlgeschlagen'], 200);
        }

        return response()->json(['status' => 'verarbeitet']);
    }

    /** HMAC-Prüfung über den unveränderten Nachrichtenkörper. */
    private function signatureMatches(Request $request, string $body, string $secret): bool
    {
        $erwartet = base64_encode(hash_hmac('sha256', $body, $secret, true));

        foreach (self::SIGNATURE_HEADERS as $header) {
            $vorhanden = (string) $request->header($header, '');
            if ($vorhanden !== '' && hash_equals($erwartet, $vorhanden)) {
                return true;
            }
        }

        return false;
    }

    /** Umschlagkennung aus den bekannten Stellen der Nachricht lesen. */
    private function envelopeId(Request $request): ?string
    {
        foreach (['data.envelopeId', 'envelopeId', 'data.envelopeSummary.envelopeId'] as $pfad) {
            $wert = $request->input($pfad);
            if (is_string($wert) && trim($wert) !== '') {
                return trim($wert);
            }
        }

        return null;
    }
}
