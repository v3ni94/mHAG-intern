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
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'context' => $context ?: null,
        ]);
    }
}
