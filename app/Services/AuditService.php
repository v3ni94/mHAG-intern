<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Zentraler Audit-Trail (Abschnitt 120 Masterprompt).
 * Einträge sind unveränderlich; kritische Aktionen werden immer protokolliert.
 */
class AuditService
{
    public static function log(
        string $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        array $context = [],
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 1000) ?: null,
            'old_values' => self::darstellbar($old) ?: null,
            'new_values' => self::darstellbar($new) ?: null,
            'context' => self::darstellbar($context) ?: null,
        ]);
    }

    /**
     * Werte so aufbereiten, dass sie als JSON gespeichert werden können.
     *
     * Hintergrund: Die Felder old_values, new_values und context sind als
     * JSON gecastet. Ein einziges ungültiges UTF-8-Byte, etwa aus einer
     * kopierten Bankauskunft oder einem Dateinamen, führt zu einer
     * JsonEncodingException. Da der Prüfpfad innerhalb der Transaktion des
     * Fachvorgangs geschrieben wird, hätte das den ganzen Vorgang abgebrochen,
     * zum Beispiel ein Zahlungsstorno.
     *
     * Ein nicht darstellbarer Wert wird ersetzt, nicht weggelassen: der
     * Prüfpfad muss erkennen lassen, dass an dieser Stelle etwas stand.
     *
     * @param  array<mixed>  $werte
     * @return array<mixed>
     */
    private static function darstellbar(array $werte): array
    {
        $bereinigt = [];

        foreach ($werte as $schluessel => $wert) {
            $schluessel = is_string($schluessel) ? self::text($schluessel) : $schluessel;

            $bereinigt[$schluessel] = match (true) {
                is_array($wert) => self::darstellbar($wert),
                is_string($wert) => self::text($wert),
                is_object($wert) => self::text((string) json_encode($wert, JSON_INVALID_UTF8_SUBSTITUTE)),
                default => $wert,
            };
        }

        return $bereinigt;
    }

    /** Zeichenkette in gültiges UTF-8 überführen, ohne den Inhalt zu verwerfen. */
    private static function text(string $wert): string
    {
        if (mb_check_encoding($wert, 'UTF-8')) {
            return $wert;
        }

        $ersetzt = mb_convert_encoding($wert, 'UTF-8', 'UTF-8');

        return $ersetzt === '' ? '[nicht darstellbarer Wert]' : $ersetzt;
    }
}
