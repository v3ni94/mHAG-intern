<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Konfigurierbare Nummernkreise (DAR-2026-00001, AR-2026-001, ...).
 * Zählerstand liegt in settings (group=sequences); Vergabe atomar.
 */
class NumberSequenceService
{
    /**
     * Nächste Nummer eines Kreises ziehen.
     * Muster: {PREFIX}-{JAHR}-{LFDn}; Zähler je Prefix+Jahr.
     */
    public static function next(string $prefix, int $digits = 5, ?int $year = null): string
    {
        $year = $year ?? now()->year;
        $key = strtolower($prefix).'_'.$year;

        return DB::transaction(function () use ($prefix, $digits, $year, $key) {
            $row = Setting::query()
                ->where('group', 'sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $current = $row ? (int) ($row->value['v'] ?? 0) : 0;
            $current++;

            Setting::query()->updateOrCreate(
                ['group' => 'sequences', 'key' => $key],
                ['value' => ['v' => $current]],
            );

            return sprintf('%s-%d-%s', strtoupper($prefix), $year, str_pad((string) $current, $digits, '0', STR_PAD_LEFT));
        });
    }
}
