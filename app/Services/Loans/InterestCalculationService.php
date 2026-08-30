<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\InterestMethod;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Taggenaue Zinsberechnung (Masterprompt Abschnitte 40-42).
 *
 * Konventionen:
 * - Zeitraum immer [from, to): from inklusiv, to exklusiv.
 * - Zinsen = Kapital * Rate/100 * dayCountFactor.
 * - Kapitalverlauf ausschliesslich aus loan_transactions (disbursement erhoeht,
 *   repayment/write_off senken; Stornos/Gegenbuchungen via reversal_of wirken
 *   auf das Kapital der stornierten Buchungsart). effective_date zaehlt.
 * - Zinssaetze aus loan_interest_terms: nach Ende eines Terms gilt der letzte
 *   gueltige Satz weiter; vor dem ersten Term gilt 0 (zinslos).
 * - Alle Berechnungen mit BCMath (Strings), niemals float.
 */
class InterestCalculationService
{
    /**
     * Buchungsarten, die das offene Kapital veraendern. Die Zinszuschreibung
     * (Zinskapitalisierung) erhoeht das Kapital; dadurch verzinsen die
     * Folgeperioden den erhoehten Betrag (Zinseszins), ohne dass die
     * Zinsrechnung selbst etwas davon wissen muss.
     */
    private const CAPITAL_TYPES = [
        BookingType::Disbursement,
        BookingType::Repayment,
        BookingType::WriteOff,
        BookingType::InterestCapitalization,
    ];

    /**
     * Tagesbruchteil fuer den Zeitraum [from, to) nach Zinsmethode.
     * Rueckgabe: Dezimalstring Skala 10.
     */
    public function dayCountFactor(InterestMethod $m, CarbonInterface $from, CarbonInterface $to): string
    {
        $from = Carbon::parse($from->toDateString());
        $to = Carbon::parse($to->toDateString());

        if ($to->lte($from)) {
            return bcadd('0', '0', Money::CALC_SCALE);
        }

        return match ($m) {
            InterestMethod::Act365 => bcdiv((string) $this->actualDays($from, $to), '365', Money::CALC_SCALE),
            InterestMethod::Act360 => bcdiv((string) $this->actualDays($from, $to), '360', Money::CALC_SCALE),
            InterestMethod::Thirty360 => bcdiv((string) $this->days30U360($from, $to), '360', Money::CALC_SCALE),
            InterestMethod::ActAct => $this->actActIsdaFactor($from, $to),
        };
    }

    /**
     * Zinsen fuer einen Zeitraum [from, to) auf konstantem Kapital.
     * principal: Dezimalstring; ratePercent: Prozent p. a., z. B. '6.000000'.
     * Rueckgabe ungerundet, Skala 10.
     */
    public function interestForPeriod(string $principal, string $ratePercent, InterestMethod $m, CarbonInterface $from, CarbonInterface $to): string
    {
        $factor = $this->dayCountFactor($m, $from, $to);
        $rate = bcdiv(Money::normalize($ratePercent, 6), '100', Money::CALC_SCALE);
        $base = bcmul(Money::normalize($principal), $rate, Money::CALC_SCALE);

        return bcmul($base, $factor, Money::CALC_SCALE);
    }

    /**
     * Zinsen eines Darlehens fuer [from, to): segmentiert nach Kapitalaenderungen
     * (loan_transactions) UND Zinssatzwechseln (loan_interest_terms).
     * Rueckgabe kaufmaennisch gerundet, 2 Nachkommastellen.
     */
    public function interestForLoanPeriod(Loan $loan, CarbonInterface $from, CarbonInterface $to): string
    {
        $from = Carbon::parse($from->toDateString());
        $to = Carbon::parse($to->toDateString());

        if ($to->lte($from)) {
            return '0.00';
        }

        $events = $this->capitalEvents($loan);
        $terms = $this->rateTerms($loan);

        // Segmentgrenzen: Periodenanfang/-ende, Kapitalaenderungen, Zinssatzwechsel.
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $points = [$fromStr, $toStr];
        foreach ($events as $event) {
            if ($event['date'] > $fromStr && $event['date'] < $toStr) {
                $points[] = $event['date'];
            }
        }
        foreach ($terms as $term) {
            if ($term['from'] > $fromStr && $term['from'] < $toStr) {
                $points[] = $term['from'];
            }
        }
        $points = array_values(array_unique($points));
        sort($points);

        $total = bcadd('0', '0', Money::CALC_SCALE);
        for ($i = 0; $i < count($points) - 1; $i++) {
            $segStart = $points[$i];
            $segEnd = $points[$i + 1];

            $capital = $this->capitalFromEvents($events, $segStart);
            if (! Money::isPositive($capital)) {
                continue; // kein (positives) Kapital, keine Zinsen
            }
            $rate = $this->rateFromTerms($terms, $segStart);
            if (bccomp(Money::normalize($rate, 6), '0', 6) === 0) {
                continue; // zinslos bzw. vor dem ersten Zinsterm
            }

            $segment = $this->interestForPeriod(
                $capital,
                $rate,
                $loan->interest_method,
                Carbon::parse($segStart),
                Carbon::parse($segEnd),
            );
            $total = bcadd($total, $segment, Money::CALC_SCALE);
        }

        return Money::round($total, 2);
    }

    /**
     * Offenes Kapital zum Stichtag (inklusive) aus loan_transactions.
     * Rueckgabe: Dezimalstring 2 NK (kann bei Datenfehlern negativ sein).
     */
    public function capitalAt(Loan $loan, CarbonInterface $date): string
    {
        return $this->capitalFromEvents($this->capitalEvents($loan), Carbon::parse($date->toDateString())->toDateString());
    }

    /**
     * Vertragszinssatz (Prozent p. a., Skala 6) zum Datum.
     * Vor dem ersten Term: '0.000000'; nach Terminende gilt der letzte Satz weiter.
     */
    public function ratePercentAt(Loan $loan, CarbonInterface $date): string
    {
        return Money::normalize($this->rateFromTerms($this->rateTerms($loan), Carbon::parse($date->toDateString())->toDateString()), 6);
    }

    /**
     * Kapitalwirksame Ereignisse chronologisch: [['date' => 'Y-m-d', 'amount' => string], ...]
     */
    public function capitalEvents(Loan $loan): array
    {
        $transactions = $loan->transactions()->with('reversalOf')->get();

        $events = [];
        foreach ($transactions as $tx) {
            if (! $this->affectsCapital($tx)) {
                continue;
            }
            $events[] = [
                'date' => $tx->effective_date->toDateString(),
                'amount' => Money::normalize($tx->amount),
            ];
        }

        usort($events, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        return $events;
    }

    protected function affectsCapital(LoanTransaction $tx): bool
    {
        if (in_array($tx->booking_type, self::CAPITAL_TYPES, true)) {
            return true;
        }

        // Storno/Korrektur wirkt auf Kapital, wenn die stornierte Buchung kapitalwirksam war.
        if (in_array($tx->booking_type, [BookingType::Cancellation, BookingType::Correction], true)
            && $tx->reversal_of !== null
            && $tx->reversalOf !== null) {
            return in_array($tx->reversalOf->booking_type, self::CAPITAL_TYPES, true);
        }

        return false;
    }

    protected function capitalFromEvents(array $events, string $dateStr): string
    {
        $capital = '0.00';
        foreach ($events as $event) {
            if ($event['date'] > $dateStr) {
                break;
            }
            $capital = Money::add($capital, $event['amount']);
        }

        return $capital;
    }

    /** Zinsterme sortiert: [['from' => 'Y-m-d', 'rate' => string], ...] */
    protected function rateTerms(Loan $loan): array
    {
        return $loan->interestTerms()
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => ['from' => $t->valid_from->toDateString(), 'rate' => Money::normalize($t->rate, 6)])
            ->all();
    }

    protected function rateFromTerms(array $terms, string $dateStr): string
    {
        $rate = '0.000000';
        foreach ($terms as $term) {
            if ($term['from'] > $dateStr) {
                break;
            }
            $rate = $term['rate'];
        }

        return $rate;
    }

    protected function actualDays(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay());
    }

    /**
     * 30/360 (US): Tag 31 wird auf 30 gesetzt, beim Enddatum nur,
     * wenn der (angepasste) Starttag 30 oder 31 ist.
     */
    protected function days30U360(CarbonInterface $from, CarbonInterface $to): int
    {
        $d1 = $from->day;
        $d2 = $to->day;
        if ($d1 === 31) {
            $d1 = 30;
        }
        if ($d2 === 31 && $d1 === 30) {
            $d2 = 30;
        }

        return 360 * ($to->year - $from->year) + 30 * ($to->month - $from->month) + ($d2 - $d1);
    }

    /**
     * ACT/ACT (ISDA): taggenau je Kalenderjahr, Schaltjahre mit Basis 366.
     */
    protected function actActIsdaFactor(CarbonInterface $from, CarbonInterface $to): string
    {
        $total = bcadd('0', '0', Money::CALC_SCALE);
        $cursor = $from->copy();

        while ($cursor->lt($to)) {
            $yearEnd = Carbon::create($cursor->year + 1, 1, 1);
            $segEnd = $yearEnd->lt($to) ? $yearEnd : $to->copy();
            $days = (string) $this->actualDays($cursor, $segEnd);
            $basis = $cursor->isLeapYear() ? '366' : '365';
            $total = bcadd($total, bcdiv($days, $basis, Money::CALC_SCALE), Money::CALC_SCALE);
            $cursor = $segEnd;
        }

        return $total;
    }
}
