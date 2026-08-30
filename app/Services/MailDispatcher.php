<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Zentraler Mailversand.
 *
 * Jeder Versand wird protokolliert (Audit-Trail) und Fehler des Mailservers
 * führen nie zum Abbruch des fachlichen Vorgangs: Eine Einladung bleibt also
 * gültig, auch wenn der Mailserver gerade nicht erreichbar ist. Der Aufrufer
 * erfährt über den Rückgabewert, ob zugestellt werden konnte, und kann eine
 * klare Meldung anzeigen.
 */
class MailDispatcher
{
    /**
     * Versendet eine Nachricht und protokolliert das Ergebnis.
     *
     * @return array{sent: bool, error: ?string}
     */
    public static function send(
        string $recipient,
        Mailable $mailable,
        string $auditAction,
        ?Model $subject = null,
        array $context = [],
    ): array {
        $context = array_merge($context, [
            'recipient' => $recipient,
            'mailable' => class_basename($mailable),
        ]);

        try {
            Mail::to($recipient)->send($mailable);

            AuditService::log($auditAction, $subject, [], [], $context + ['result' => 'versendet']);

            return ['sent' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::error('Mailversand fehlgeschlagen', [
                'recipient' => $recipient,
                'mailable' => class_basename($mailable),
                'error' => $e->getMessage(),
            ]);

            AuditService::log($auditAction.'_failed', $subject, [], [], $context + [
                'result' => 'fehlgeschlagen',
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'error' => self::readableError($e)];
        }
    }

    /**
     * Technische Fehlermeldungen in verständliche Hinweise übersetzen.
     */
    private static function readableError(Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'Authentication') || str_contains($message, '535') => 'Der Mailserver hat die Anmeldedaten abgelehnt. Bitte MAIL_USERNAME und MAIL_PASSWORD prüfen.',
            str_contains($message, 'Connection could not be established') || str_contains($message, 'Connection refused') => 'Der Mailserver ist nicht erreichbar. Bitte MAIL_HOST und MAIL_PORT prüfen.',
            str_contains($message, 'SSL') || str_contains($message, 'TLS') => 'Die verschlüsselte Verbindung zum Mailserver ist fehlgeschlagen. Bei Port 465 muss MAIL_SCHEME=smtps gesetzt sein, bei Port 587 MAIL_SCHEME=smtp.',
            str_contains($message, 'timed out') => 'Die Verbindung zum Mailserver hat die Zeitgrenze überschritten.',
            default => 'Technische Meldung: '.$message,
        };
    }
}
