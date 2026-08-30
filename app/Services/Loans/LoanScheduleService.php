<?php

namespace App\Services\Loans;

use App\Enums\InterestDueDayMode;
use App\Enums\InterestFrequency;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Enums\RepaymentModel;
use App\Models\Loan;
use App\Models\RepaymentPlanItem;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Erzeugt und aktualisiert die SOLL-Zeilen des Zahlungsplans
 * (repayment_plan_items) aus den Vertragsdaten (Masterprompt Abschnitte 23-24, 45).
 *
 * Regeln:
 * - Zins-SOLL je Periode gem. interest_frequency ab effective_from;
 *   Periodenende = Faelligkeitstag, Betrag aus interestForLoanPeriod (Kapitalverlauf!).
 * - Tilgungs-SOLL gem. repayment_model (bullet/installment/annuity);
 *   open_ended/frame/current_account/custom: keine automatischen Tilgungszeilen.
 * - Gebuehren aus loan_fees (einmalig bzw. wiederkehrend).
 * - Horizont: bis contract_end/due_date, sonst rollierend +12 Monate.
 * - Zeilen mit manually_adjusted=true oder erfasstem IST
 *   (Status nicht planned/assumed) werden NIE ueberschrieben oder geloescht.
 */
class LoanScheduleService
{
    public function __construct(protected InterestCalculationService $interest) {}

    public function generate(Loan $loan): void
    {
        $targets = array_merge(
            $this->interestTargets($loan),
            $this->principalTargets($loan),
            $this->feeTargets($loan),
        );

        $this->syncItems($loan, $targets);
    }

    /**
     * Grundannahme planmaessiger Vertragserfuellung (Abschnitt 24):
     * vergangene planned-Zeilen (due_date <= asOf) werden auf
     * status=assumed, origin=assumed gesetzt. Zeilen mit IST bleiben unberuehrt.
     */
    public function rollForwardAssumed(Loan $loan, ?CarbonInterface $asOf = null): void
    {
        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();

        $loan->repaymentPlanItems()
            ->where('status', RepaymentItemStatus::Planned->value)
            ->whereDate('due_date', '<=', $asOfStr)
            ->update([
                'status' => RepaymentItemStatus::Assumed->value,
                'origin' => PaymentOrigin::Assumed->value,
                'updated_at' => now(),
            ]);
    }

    // ------------------------------------------------------------------
    // SOLL-Ziele
    // ------------------------------------------------------------------

    /**
     * Zinsperioden des Darlehens in zeitlicher Ordnung, lueckenlos und
     * ueberschneidungsfrei. Die Periode endet am Faelligkeitstag
     * einschliesslich; 'end_excl' ist der Folgetag, weil die Zinsrechnung mit
     * halboffenen Intervallen [von, bis) arbeitet.
     *
     * Wird ausserdem von der Zinskapitalisierung genutzt, damit es nur eine
     * Stelle gibt, die Perioden und Faelligkeiten festlegt.
     *
     * @return array<int, array{start: string, end_excl: string, due: string}>
     */
    public function interestPeriods(Loan $loan): array
    {
        $months = $this->monthsPerPeriod($loan->interest_frequency);
        $start = Carbon::parse($loan->effective_from->toDateString());

        if ($months === null) {
            if ($loan->interest_frequency === InterestFrequency::AtMaturity) {
                $maturity = $loan->due_date ?? $loan->contract_end;
                if ($maturity) {
                    $maturity = Carbon::parse($maturity->toDateString());
                    // Ausfall begrenzt auch die endfaellige Zinsperiode
                    if ($loan->defaulted_on !== null) {
                        $defaultDay = Carbon::parse($loan->defaulted_on->toDateString());
                        if ($defaultDay->lt($maturity)) {
                            $maturity = $defaultDay;
                        }
                    }

                    return [[
                        'start' => $start->toDateString(),
                        'end_excl' => $maturity->copy()->addDay()->toDateString(),
                        'due' => $maturity->toDateString(),
                    ]];
                }
            }

            return []; // custom: keine automatischen Zinszeilen
        }

        $hardEnd = $loan->contract_end ?? $loan->due_date;

        // Ausfall (Anforderung 30.08.2026): ab dem Ausfalldatum entstehen
        // KEINE weiteren Soll-Zinsen. Bereits entstandene bleiben erhalten,
        // die laufende Periode endet am Ausfalltag. Zinsen nach dem Ausfall
        // waeren eine Forderung, die das System nicht unterstellen darf.
        if ($loan->defaulted_on !== null) {
            $defaultDay = Carbon::parse($loan->defaulted_on->toDateString());
            $hardEnd = ($hardEnd === null || $defaultDay->lt(Carbon::parse($hardEnd->toDateString())))
                ? $defaultDay
                : $hardEnd;
        }

        $horizon = $hardEnd
            ? Carbon::parse($hardEnd->toDateString())
            : today()->addMonthsNoOverflow(12);
        $limitExcl = $horizon->copy()->addDay();

        // Das Raster der Faelligkeiten richtet sich nach interest_due_day_mode;
        // die Berechnung bleibt taggenau.
        $periods = [];
        $pStart = $start->copy();
        foreach ($this->dueDateGrid($loan, $months, $start) as $gridDue) {
            if ($pStart->gte($limitExcl)) {
                break;
            }
            $due = $gridDue->copy();
            $pEnd = $due->copy()->addDay();
            if ($hardEnd && $pEnd->gt($limitExcl)) {
                $pEnd = $limitExcl->copy(); // Stummelperiode bis Vertragsende
                $due = $horizon->copy();
            }
            if ($pEnd->lte($pStart)) {
                continue; // Faelligkeit liegt vor dem Periodenbeginn
            }
            if (! $hardEnd && $due->gt($horizon)) {
                break; // rollierender Horizont: nur vollstaendige Perioden
            }

            $periods[] = [
                'start' => $pStart->toDateString(),
                'end_excl' => $pEnd->toDateString(),
                'due' => $due->toDateString(),
            ];
            $pStart = $pEnd->copy();
        }

        return $periods;
    }

    /** @return array<int, array{0: RepaymentItemType, 1: string, 2: string}> */
    protected function interestTargets(Loan $loan): array
    {
        $targets = [];

        foreach ($this->interestPeriods($loan) as $period) {
            $amount = $this->interest->interestForLoanPeriod(
                $loan,
                Carbon::parse($period['start']),
                Carbon::parse($period['end_excl']),
            );
            if (Money::isPositive($amount)) {
                $targets[] = [RepaymentItemType::Interest, $period['due'], $amount];
            }
        }

        return $targets;
    }

    /**
     * Faelligkeitstage der Zinsperioden im eingestellten Raster, aufsteigend.
     *
     * effective_from: bisheriges Verhalten, Raster aus dem Wirkungsbeginn.
     * fixed_day:      fester Tag im Monat (1 bis 28).
     * month_end:      letzter Tag des Monats.
     *
     * Der erste Faelligkeitstag ist der erste des Rasters, der nicht vor dem
     * Wirkungsbeginn liegt; die erste Periode kann dadurch kuerzer oder
     * laenger als die Folgeperioden sein (Stummelperiode). Das ist gewollt:
     * die Zinsen werden taggenau gerechnet, nur der Faelligkeitstag ist
     * vorgegeben.
     *
     * Zusaetzlich kann bei einer Zinsfaelligkeit von drei Monaten und mehr ein
     * Faelligkeitsmonat vorgegeben werden (interest_due_month). Erst damit
     * laesst sich eine Vereinbarung wie "jaehrlich zum 31.12." darstellen,
     * unabhaengig davon, in welchem Monat das Darlehen beginnt.
     *
     * @return \Generator<int, Carbon>
     */
    protected function dueDateGrid(Loan $loan, int $months, Carbon $start): \Generator
    {
        $mode = $loan->interest_due_day_mode ?? InterestDueDayMode::EffectiveFrom;
        $fixedDay = (int) ($loan->interest_due_day ?? 0);
        $dueMonth = (int) ($loan->interest_due_month ?? 0);

        // Fester Tag ohne erfassten Tag: kein Raten, es bleibt beim Standard.
        if ($mode === InterestDueDayMode::FixedDay
            && ($fixedDay < InterestDueDayMode::FIXED_DAY_MIN || $fixedDay > InterestDueDayMode::FIXED_DAY_MAX)) {
            $mode = InterestDueDayMode::EffectiveFrom;
        }

        // Der Faelligkeitsmonat wirkt nur bei mehrmonatigen Perioden und nur
        // zusammen mit einem festen Tag oder dem Monatsletzten; beim Raster
        // aus dem Wirkungsbeginn hat er keine Bedeutung.
        $useDueMonth = $months >= 3
            && $dueMonth >= 1 && $dueMonth <= 12
            && $mode !== InterestDueDayMode::EffectiveFrom;

        $first = $useDueMonth
            ? $this->firstDueInMonthGrid($start, $months, $dueMonth, $mode, $fixedDay)
            : match ($mode) {
                InterestDueDayMode::MonthEnd => $start->copy()->endOfMonth()->startOfDay(),
                InterestDueDayMode::FixedDay => $this->firstFixedDayDue($start, $fixedDay, $months),
                InterestDueDayMode::EffectiveFrom => $start->copy()->addMonthsNoOverflow($months)->subDay(),
            };

        for ($k = 0; $k < 1200; $k++) {
            yield $mode === InterestDueDayMode::MonthEnd
                ? $first->copy()->startOfMonth()->addMonthsNoOverflow($months * $k)->endOfMonth()->startOfDay()
                : $first->copy()->addMonthsNoOverflow($months * $k);
        }
    }

    /**
     * Erster fester Faelligkeitstag ab dem Wirkungsbeginn. Liegt der Tag im
     * Monat des Wirkungsbeginns bereits davor, wird eine Periode weitergerueckt.
     */
    protected function firstFixedDayDue(Carbon $start, int $day, int $months): Carbon
    {
        $first = $start->copy()->startOfDay()->day($day);
        if ($first->lt($start)) {
            $first = $first->addMonthsNoOverflow($months);
        }

        return $first;
    }

    /**
     * Erster Faelligkeitstag eines Rasters, das im vorgegebenen
     * Faelligkeitsmonat verankert ist. Gesucht wird der erste Rastertag, der
     * nicht vor dem Wirkungsbeginn liegt; begonnen wird ein Jahr davor, damit
     * auch Quartals- und Halbjahresraster sicher getroffen werden.
     */
    protected function firstDueInMonthGrid(
        Carbon $start,
        int $months,
        int $dueMonth,
        InterestDueDayMode $mode,
        int $fixedDay,
    ): Carbon {
        $base = Carbon::createFromDate($start->year - 1, $dueMonth, 1)->startOfDay();
        $steps = (int) ceil(12 / $months) + 2;

        for ($k = 0; $k <= $steps; $k++) {
            $monthStart = $base->copy()->addMonthsNoOverflow($months * $k);
            $due = $mode === InterestDueDayMode::MonthEnd
                ? $monthStart->copy()->endOfMonth()->startOfDay()
                : $monthStart->copy()->day($fixedDay);

            if ($due->gte($start)) {
                return $due;
            }
        }

        // Rechnerisch nicht erreichbar; als Rueckfall das bisherige Verhalten.
        return $start->copy()->addMonthsNoOverflow($months)->subDay();
    }

    /** @return array<int, array{0: RepaymentItemType, 1: string, 2: string}> */
    protected function principalTargets(Loan $loan): array
    {
        $principal = Money::normalize($loan->principal_amount);
        if (! Money::isPositive($principal)) {
            return [];
        }

        return match ($loan->repayment_model) {
            RepaymentModel::Bullet => $this->bulletTargets($loan, $principal),
            RepaymentModel::Installment => $this->installmentTargets($loan, $principal),
            RepaymentModel::Annuity => $this->annuityTargets($loan, $principal),
            default => [], // open_ended, frame, current_account, custom
        };
    }

    protected function bulletTargets(Loan $loan, string $principal): array
    {
        $due = $loan->due_date ?? $loan->contract_end;
        if (! $due) {
            return [];
        }

        return [[RepaymentItemType::Principal, Carbon::parse($due->toDateString())->toDateString(), $principal]];
    }

    protected function installmentTargets(Loan $loan, string $principal): array
    {
        $dates = $this->periodDueDates($loan);
        $n = count($dates);
        if ($n === 0) {
            return [];
        }

        $per = Money::round(bcdiv($principal, (string) $n, Money::CALC_SCALE), 2);
        $targets = [];
        $sum = '0.00';
        foreach ($dates as $i => $due) {
            $amount = ($i === $n - 1) ? Money::sub($principal, $sum) : $per;
            $sum = Money::add($sum, $amount);
            if (Money::isPositive($amount)) {
                $targets[] = [RepaymentItemType::Principal, $due, $amount];
            }
        }

        return $targets;
    }

    /**
     * Annuitaet: A = P * i * q^n / (q^n - 1), i = Periodenzins,
     * Tilgungsanteil je Periode = A - Zins auf Restschuld, Rundung 2 NK,
     * letzte Rate = verbleibende Restschuld.
     */
    protected function annuityTargets(Loan $loan, string $principal): array
    {
        $dates = $this->periodDueDates($loan);
        $n = count($dates);
        if ($n === 0) {
            return [];
        }

        $months = $this->monthsPerPeriod($loan->interest_frequency) ?? 12;
        $periodsPerYear = (string) intdiv(12, $months);
        $ratePercent = $this->interest->ratePercentAt($loan, Carbon::parse($loan->effective_from->toDateString()));
        $i = bcdiv(bcdiv($ratePercent, '100', Money::CALC_SCALE), $periodsPerYear, Money::CALC_SCALE);

        if (bccomp($i, '0', Money::CALC_SCALE) === 0) {
            return $this->installmentTargets($loan, $principal); // zinslos: gleiche Raten
        }

        $q = bcpow(bcadd('1', $i, Money::CALC_SCALE), (string) $n, Money::CALC_SCALE);
        $denominator = bcsub($q, '1', Money::CALC_SCALE);
        $annuity = Money::round(
            bcdiv(bcmul(bcmul($principal, $i, Money::CALC_SCALE), $q, Money::CALC_SCALE), $denominator, Money::CALC_SCALE),
            2,
        );

        $targets = [];
        $balance = $principal;
        foreach ($dates as $idx => $due) {
            if (! Money::isPositive($balance)) {
                break;
            }
            if ($idx === $n - 1) {
                $portion = $balance; // letzte Rate = Restschuld
            } else {
                $interestPart = Money::round(bcmul($balance, $i, Money::CALC_SCALE), 2);
                $portion = Money::sub($annuity, $interestPart);
                if (Money::cmp($portion, $balance) > 0) {
                    $portion = $balance;
                }
            }
            if (Money::isPositive($portion)) {
                $targets[] = [RepaymentItemType::Principal, $due, $portion];
            }
            $balance = Money::sub($balance, $portion);
        }

        return $targets;
    }

    /** @return array<int, array{0: RepaymentItemType, 1: string, 2: string}> */
    protected function feeTargets(Loan $loan): array
    {
        $targets = [];
        $effectiveFrom = Carbon::parse($loan->effective_from->toDateString());
        $hardEnd = $loan->contract_end ?? $loan->due_date;
        $horizon = $hardEnd
            ? Carbon::parse($hardEnd->toDateString())
            : today()->addMonthsNoOverflow(12);

        foreach ($loan->fees()->orderBy('id')->get() as $fee) {
            $amount = $this->feeAmount($fee->amount, $fee->percentage, $loan);
            if (! Money::isPositive($amount)) {
                continue;
            }

            $months = match ($fee->recurrence) {
                'monthly' => 1,
                'quarterly' => 3,
                'annual' => 12,
                default => null, // one_time
            };

            if ($months === null) {
                $due = $fee->due_date ? Carbon::parse($fee->due_date->toDateString()) : $effectiveFrom;
                $targets[] = [RepaymentItemType::Fee, $due->toDateString(), $amount];

                continue;
            }

            if ($fee->due_date) {
                // Erste Faelligkeit laut Gebuehr, dann je Wiederholungsperiode
                $anchor = Carbon::parse($fee->due_date->toDateString());
                for ($k = 0; $k < 1200; $k++) {
                    $due = $anchor->copy()->addMonthsNoOverflow($months * $k);
                    if ($due->gt($horizon)) {
                        break;
                    }
                    $targets[] = [RepaymentItemType::Fee, $due->toDateString(), $amount];
                }
            } else {
                // Analog zu Zinsperioden: Faelligkeit am Periodenende ab Wirkungsbeginn
                for ($k = 0; $k < 1200; $k++) {
                    $due = $effectiveFrom->copy()->addMonthsNoOverflow($months * ($k + 1))->subDay();
                    if ($due->gt($horizon)) {
                        break;
                    }
                    $targets[] = [RepaymentItemType::Fee, $due->toDateString(), $amount];
                }
            }
        }

        return $targets;
    }

    protected function feeAmount(?string $amount, ?string $percentage, Loan $loan): string
    {
        if ($amount !== null && ! Money::isZero($amount)) {
            return Money::normalize($amount);
        }
        if ($percentage !== null) {
            $factor = bcdiv(Money::normalize($percentage, 6), '100', Money::CALC_SCALE);

            return Money::round(bcmul($factor, Money::normalize($loan->principal_amount), Money::CALC_SCALE), 2);
        }

        return '0.00';
    }

    // ------------------------------------------------------------------
    // Abgleich mit Bestand
    // ------------------------------------------------------------------

    /**
     * Faelligkeitstermine der Tilgungsperioden bis zum harten Vertragsende
     * (inkl. Stummelperiode). Ohne Vertragsende/Faelligkeit: keine Termine.
     *
     * @return array<int, string>
     */
    protected function periodDueDates(Loan $loan): array
    {
        $hardEnd = $loan->contract_end ?? $loan->due_date;
        if (! $hardEnd) {
            return [];
        }
        $hardEnd = Carbon::parse($hardEnd->toDateString());
        $months = $this->monthsPerPeriod($loan->interest_frequency);
        if ($months === null) {
            return [$hardEnd->toDateString()];
        }

        $start = Carbon::parse($loan->effective_from->toDateString());
        $limitExcl = $hardEnd->copy()->addDay();
        $dates = [];

        // Tilgungsraten folgen demselben Faelligkeitsraster wie die Zinsen,
        // damit Zins und Tilgung einer Periode am gleichen Tag faellig werden.
        $pStart = $start->copy();
        foreach ($this->dueDateGrid($loan, $months, $start) as $gridDue) {
            if ($pStart->gte($limitExcl)) {
                break;
            }
            $due = $gridDue->copy();
            $pEnd = $due->copy()->addDay();
            if ($pEnd->gt($limitExcl)) {
                $pEnd = $limitExcl->copy();
                $due = $hardEnd->copy();
            }
            if ($pEnd->lte($pStart)) {
                continue;
            }
            $dates[] = $due->toDateString();
            $pStart = $pEnd->copy();
        }

        return $dates;
    }

    protected function monthsPerPeriod(InterestFrequency $frequency): ?int
    {
        return match ($frequency) {
            InterestFrequency::Monthly => 1,
            InterestFrequency::Quarterly => 3,
            InterestFrequency::Semiannual => 6,
            InterestFrequency::Annual => 12,
            default => null,
        };
    }

    /**
     * Abgleich der SOLL-Ziele mit dem Bestand je (Art, Faelligkeit):
     * - geschuetzte Zeilen (manually_adjusted oder IST erfasst) bleiben unberuehrt
     *   und "verbrauchen" je ein Ziel;
     * - systemgenerierte Zeilen (planned/assumed) werden aktualisiert,
     *   fehlende erzeugt, ueberzaehlige geloescht.
     *
     * @param  array<int, array{0: RepaymentItemType, 1: string, 2: string}>  $targets
     */
    protected function syncItems(Loan $loan, array $targets): void
    {
        $targetGroups = [];
        foreach ($targets as [$type, $due, $amount]) {
            $targetGroups[$type->value.'|'.$due][] = $amount;
        }

        $existingGroups = [];
        foreach ($loan->repaymentPlanItems()->orderBy('due_date')->orderBy('id')->get() as $item) {
            $existingGroups[$item->item_type->value.'|'.$item->due_date->toDateString()][] = $item;
        }

        $keys = array_unique(array_merge(array_keys($targetGroups), array_keys($existingGroups)));

        foreach ($keys as $key) {
            $amounts = $targetGroups[$key] ?? [];
            $items = $existingGroups[$key] ?? [];

            $protectedCount = count(array_filter($items, fn (RepaymentPlanItem $i) => ! $this->isSystemManaged($i)));
            $system = array_values(array_filter($items, fn (RepaymentPlanItem $i) => $this->isSystemManaged($i)));

            // Geschuetzte Zeilen decken je ein Ziel ab, werden aber nie angefasst.
            array_splice($amounts, 0, $protectedCount);

            $max = max(count($amounts), count($system));
            for ($i = 0; $i < $max; $i++) {
                $amount = $amounts[$i] ?? null;
                $item = $system[$i] ?? null;

                if ($amount !== null && $item !== null) {
                    if (Money::cmp($item->planned_amount, $amount) !== 0) {
                        $item->update(['planned_amount' => $amount]);
                    }
                } elseif ($amount !== null) {
                    [$typeValue, $due] = explode('|', $key, 2);
                    $loan->repaymentPlanItems()->create([
                        'item_type' => $typeValue,
                        'due_date' => $due,
                        'planned_amount' => $amount,
                        'status' => RepaymentItemStatus::Planned,
                        'origin' => PaymentOrigin::Assumed,
                    ]);
                } elseif ($item !== null) {
                    $item->delete(); // abgeleitete SOLL-Zeile ohne Ziel entfernen
                }
            }
        }
    }

    protected function isSystemManaged(RepaymentPlanItem $item): bool
    {
        return ! $item->manually_adjusted
            && in_array($item->status, [RepaymentItemStatus::Planned, RepaymentItemStatus::Assumed], true);
    }
}
