<?php

namespace App\Services;

use App\Models\DailyFact;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tagesereignisse für die Fußzeile (Abschnitt 119, erweitert am 30.08.2026).
 *
 * Für jeden Kalendertag wird ein Aktionstag angezeigt, zum Beispiel der
 * Welthundetag. Grundsätze:
 *
 * - Es wird nichts erfunden. Angezeigt wird nur, was gepflegt und aktiv ist;
 *   die Quelle ist Pflichtfeld. Ist für einen Tag nichts erfasst, bleibt die
 *   Fußzeile an dieser Stelle leer.
 * - Wiederkehrende Einträge gelten über Monat und Tag, einmalige über ein
 *   konkretes Datum. Ein Eintrag für ein konkretes Datum hat Vorrang, weil er
 *   der genauere ist.
 * - Die Auswahl ist bei mehreren Einträgen je Tag deterministisch (kleinste
 *   ID), damit die Fußzeile innerhalb eines Tages nicht wechselt.
 */
class DailyEventService
{
    /** Anzahl der Kalendertage einschließlich 29. Februar. */
    public const KALENDERTAGE = 366;

    /**
     * Ereignis des Tages und die Anzahl weiterer Einträge für denselben Tag.
     *
     * @return array{event: ?DailyFact, others: Collection<int, DailyFact>}
     */
    public function forDate(?CarbonInterface $date = null): array
    {
        $tag = $date ? Carbon::parse($date->toDateString()) : today();

        $treffer = DailyFact::query()
            ->where('is_active', true)
            ->where(function ($q) use ($tag) {
                $q->where(function ($qq) use ($tag) {
                    $qq->where('recurring', true)->where('month_day', $tag->format('m-d'));
                })->orWhere(function ($qq) use ($tag) {
                    $qq->where('recurring', false)->whereDate('specific_date', $tag->toDateString());
                });
            })
            // Einmalige Eintraege zuerst (recurring = 0): das konkrete Datum
            // ist die genauere Angabe. Danach nach ID, damit die Auswahl
            // innerhalb eines Tages nicht wechselt.
            ->orderBy('recurring')
            ->orderBy('id')
            ->get();

        return [
            'event' => $treffer->first(),
            'others' => $treffer->slice(1)->values(),
        ];
    }

    /**
     * Abdeckung des Kalenderjahres: welche Tage haben einen aktiven,
     * wiederkehrenden Eintrag und welche nicht. Grundlage für die
     * Pflegeoberfläche, damit Lücken sichtbar sind.
     *
     * @return array{covered: int, total: int, entries: int, gaps: array<string, array<int, string>>}
     */
    public function coverage(): array
    {
        $belegt = DailyFact::query()
            ->where('is_active', true)
            ->where('recurring', true)
            ->pluck('month_day')
            ->unique()
            ->all();
        $belegt = array_flip($belegt);

        $anzahl = DailyFact::query()->where('is_active', true)->where('recurring', true)->count();

        $gaps = [];
        foreach (self::monatstage() as $monat => $tage) {
            $fehlend = [];
            for ($tag = 1; $tag <= $tage; $tag++) {
                $md = $monat.'-'.str_pad((string) $tag, 2, '0', STR_PAD_LEFT);
                if (! isset($belegt[$md])) {
                    $fehlend[] = $md;
                }
            }
            if ($fehlend !== []) {
                $gaps[$monat] = $fehlend;
            }
        }

        $luecken = array_sum(array_map('count', $gaps));

        return [
            'covered' => self::KALENDERTAGE - $luecken,
            'total' => self::KALENDERTAGE,
            'entries' => $anzahl,
            'gaps' => $gaps,
        ];
    }

    /**
     * Tage je Monat, Februar mit 29 Tagen, damit der 29.02. pflegbar bleibt.
     *
     * @return array<string, int>
     */
    public static function monatstage(): array
    {
        return [
            '01' => 31, '02' => 29, '03' => 31, '04' => 30, '05' => 31, '06' => 30,
            '07' => 31, '08' => 31, '09' => 30, '10' => 31, '11' => 30, '12' => 31,
        ];
    }

    /** @return array<string, string> Monatsnummer auf deutschen Namen */
    public static function monatsnamen(): array
    {
        return [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
        ];
    }
}
