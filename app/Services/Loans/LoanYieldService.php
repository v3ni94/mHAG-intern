<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Models\Loan;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Ertrag und Rendite eines Darlehens (Anforderung vom 30.08.2026).
 *
 * Jede Kennzahl wird mit ihren Bestandteilen zurückgegeben, damit die
 * Oberfläche den Rechenweg anzeigen kann und keine unerklärte Zahl im Raum
 * steht (Masterprompt Abschnitt 140).
 *
 * Grundsätze:
 * - Bestätigte und systemseitig angenommene Zahlungen werden NIE vermischt
 *   (Abschnitt 24). Der Ertrag wird deshalb zweimal ausgewiesen: nur
 *   bestätigt und einschließlich Annahmen.
 * - Kapitalisierte Zinsen sind Ertrag, auch wenn kein Geld geflossen ist:
 *   sie erhöhen die Forderung.
 * - Die Effektivrendite (interner Zinsfuß) ist eine rechnerische Kennzahl,
 *   keine Bonitäts- oder Wertaussage. Ist sie nicht ermittelbar, wird das
 *   gesagt, statt eine Zahl zu erfinden.
 * - Alle Geldbeträge als Dezimalstrings, Rechnung mit BCMath.
 */
class LoanYieldService
{
    /** Zulaessiger Bereich der Effektivrendite p. a. als Faktor (-99 % bis +1000 %). */
    public const IRR_MIN = '-0.99';

    public const IRR_MAX = '10.00';

    /**
     * Intervallhalbierung auf dem TAGESZINSSATZ. Der Tagessatz erlaubt
     * ganzzahlige Exponenten in bcpow und damit eine reine BCMath-Rechnung
     * ohne Gleitkommazahlen. Die Grenzen entsprechen ungefaehr -99,9 % bis
     * +137.600 % p. a. und schliessen den zulaessigen Bereich sicher ein.
     */
    private const DAILY_MIN = '-0.02';

    private const DAILY_MAX = '0.02';

    private const SCALE = 12;

    private const TOLERANCE = '0.0000000001';

    private const MAX_ITERATIONS = 200;

    public function __construct(
        protected LoanBalanceService $balance,
        protected InterestCalculationService $interest,
    ) {}

    /**
     * @return array{
     *     as_of: string, period_from: ?string, period_to: string, period_days: int,
     *     interest_confirmed: string, interest_assumed: string, interest_capitalized: string,
     *     fees_confirmed: string, fees_assumed: string,
     *     yield_confirmed: string, yield_assumed: string, yield_total: string,
     *     average_capital: string, year_fraction: string, day_count_label: string,
     *     return_pa: ?string, return_pa_total: ?string,
     *     irr: ?string, irr_note: ?string, cash_flows: array<int, array{date: string, amount: string, label: string}>,
     *     receivable: string
     * }
     */
    public function analyse(Loan $loan, ?CarbonInterface $asOf = null): array
    {
        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();
        $balances = $this->balance->balances($loan, Carbon::parse($asOfStr));

        $interestConfirmed = $balances['interest_confirmed'];
        $interestAssumed = $balances['interest_assumed'];
        $interestCapitalized = $balances['interest_capitalized'];
        $feesConfirmed = $balances['fees_confirmed'];
        $feesAssumed = $balances['fees_assumed'];

        // Ertrag: bestaetigte Zahlungen und Zuschreibungen sind belegt,
        // Annahmen werden gesondert gefuehrt und nie beigemischt.
        $yieldConfirmed = Money::add(Money::add($interestConfirmed, $interestCapitalized), $feesConfirmed);
        $yieldAssumed = Money::add($interestAssumed, $feesAssumed);
        $yieldTotal = Money::add($yieldConfirmed, $yieldAssumed);

        $events = $this->interest->capitalEvents($loan);
        [$periodFrom, $periodDays, $averageCapital] = $this->averageCapital($events, $asOfStr);

        $yearFraction = '0.0000000000';
        if ($periodFrom !== null && $periodDays > 0) {
            $yearFraction = $this->interest->dayCountFactor(
                $loan->interest_method,
                Carbon::parse($periodFrom),
                Carbon::parse($asOfStr)->addDay(),
            );
        }

        $returnPa = $this->annualReturn($yieldConfirmed, $averageCapital, $yearFraction);
        $returnPaTotal = $this->annualReturn($yieldTotal, $averageCapital, $yearFraction);

        $cashFlows = $this->cashFlows($loan, $asOfStr, $balances['total_receivable']);
        [$irr, $irrNote] = $this->internalRateOfReturn($cashFlows);

        return [
            'as_of' => $asOfStr,
            'period_from' => $periodFrom,
            'period_to' => $asOfStr,
            'period_days' => $periodDays,
            'interest_confirmed' => $interestConfirmed,
            'interest_assumed' => $interestAssumed,
            'interest_capitalized' => $interestCapitalized,
            'fees_confirmed' => $feesConfirmed,
            'fees_assumed' => $feesAssumed,
            'yield_confirmed' => $yieldConfirmed,
            'yield_assumed' => $yieldAssumed,
            'yield_total' => $yieldTotal,
            'average_capital' => $averageCapital,
            'year_fraction' => $yearFraction,
            'day_count_label' => $loan->interest_method->label(),
            'return_pa' => $returnPa,
            'return_pa_total' => $returnPaTotal,
            'irr' => $irr,
            'irr_note' => $irrNote,
            'cash_flows' => $cashFlows,
            'receivable' => $balances['total_receivable'],
        ];
    }

    /**
     * Durchschnittlich gebundenes Kapital: zeitgewichteter Mittelwert des
     * offenen Kapitals. Betrachtungszeitraum beginnt am ersten Tag mit
     * positivem Kapital (erste Auszahlung) und endet am Stichtag
     * einschliesslich. Vor der ersten Auszahlung ist kein Kapital gebunden;
     * dieser Zeitraum wuerde den Mittelwert ohne Aussagewert verwaessern.
     *
     * @param  array<int, array{date: string, amount: string}>  $events
     * @return array{0: ?string, 1: int, 2: string} [Beginn, Tage, Mittelwert]
     */
    protected function averageCapital(array $events, string $asOfStr): array
    {
        $start = null;
        $running = '0.00';
        foreach ($events as $event) {
            $running = Money::add($running, $event['amount']);
            if (Money::isPositive($running)) {
                $start = $event['date'];
                break;
            }
        }

        if ($start === null || $start > $asOfStr) {
            return [null, 0, '0.00'];
        }

        $endExcl = Carbon::parse($asOfStr)->addDay()->toDateString();

        $points = [$start, $endExcl];
        foreach ($events as $event) {
            if ($event['date'] > $start && $event['date'] < $endExcl) {
                $points[] = $event['date'];
            }
        }
        $points = array_values(array_unique($points));
        sort($points);

        $weighted = bcadd('0', '0', Money::CALC_SCALE);
        $totalDays = 0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $days = (int) Carbon::parse($points[$i])->diffInDays(Carbon::parse($points[$i + 1]));
            if ($days <= 0) {
                continue;
            }
            $capital = $this->capitalAt($events, $points[$i]);
            $weighted = bcadd($weighted, bcmul($capital, (string) $days, Money::CALC_SCALE), Money::CALC_SCALE);
            $totalDays += $days;
        }

        $average = $totalDays > 0
            ? Money::round(bcdiv($weighted, (string) $totalDays, Money::CALC_SCALE), 2)
            : '0.00';

        return [$start, $totalDays, $average];
    }

    /**
     * @param  array<int, array{date: string, amount: string}>  $events  chronologisch
     */
    protected function capitalAt(array $events, string $dateStr): string
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

    /**
     * Rendite p. a. = Ertrag / durchschnittlich gebundenes Kapital,
     * hochgerechnet über den Jahresbruchteil der Zinsmethode.
     * Rueckgabe: Prozentsatz mit 4 Nachkommastellen, null wenn nicht
     * berechenbar (kein gebundenes Kapital oder kein Zeitraum).
     */
    protected function annualReturn(string $yield, string $averageCapital, string $yearFraction): ?string
    {
        if (! Money::isPositive($averageCapital)) {
            return null;
        }
        if (bccomp($yearFraction, '0', Money::CALC_SCALE) <= 0) {
            return null;
        }

        $quotient = bcdiv(Money::normalize($yield, Money::CALC_SCALE), Money::normalize($averageCapital, Money::CALC_SCALE), Money::CALC_SCALE);
        $perYear = bcdiv($quotient, $yearFraction, Money::CALC_SCALE);

        return Money::round(bcmul($perYear, '100', Money::CALC_SCALE), 4);
    }

    /**
     * Zahlungsstroeme aus Sicht des Darlehensgebers, aus dem Darlehenskonto:
     * Auszahlungen negativ, Zahlungseingaenge positiv, Restforderung zum
     * Stichtag als Schlussbetrag.
     *
     * Als Zahlungsstrom gilt eine Buchung, die aus einer Zahlung stammt
     * (Geldeingang, auch Stornos davon) oder eine Auszahlung ist. Nicht
     * zahlungswirksame Buchungen wie Sollstellungen, Zinszuschreibungen und
     * Abschreibungen bleiben aussen vor; sie wirken ueber die Restforderung.
     *
     * @return array<int, array{date: string, amount: string, label: string}>
     */
    public function cashFlows(Loan $loan, string $asOfStr, string $receivable): array
    {
        $paymentClass = (new Payment)->getMorphClass();
        $flows = [];

        foreach ($loan->transactions()->with('reversalOf')->orderBy('effective_date')->orderBy('id')->get() as $tx) {
            if ($tx->effective_date->toDateString() > $asOfStr) {
                continue;
            }

            $base = $tx->booking_type;
            if (in_array($base, [BookingType::Cancellation, BookingType::Correction], true) && $tx->reversalOf) {
                $base = $tx->reversalOf->booking_type;
            }

            $isCash = $tx->source_type === $paymentClass || $base === BookingType::Disbursement;
            if (! $isCash) {
                continue;
            }

            // Kontosicht: + erhoeht die Forderung. Zahlungssicht des Gebers:
            // eine Auszahlung ist Geldabgang, eine Zahlung ist Geldeingang.
            $flows[] = [
                'date' => $tx->effective_date->toDateString(),
                'amount' => Money::negate($tx->amount),
                'label' => $tx->booking_type->label(),
            ];
        }

        if (Money::isPositive($receivable)) {
            $flows[] = [
                'date' => $asOfStr,
                'amount' => Money::normalize($receivable),
                'label' => 'Restforderung zum Stichtag',
            ];
        }

        usort($flows, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        return $flows;
    }

    /**
     * Interner Zinsfuss (Effektivrendite p. a.) aus den Zahlungsstroemen,
     * numerisch über Intervallhalbierung mit BCMath.
     *
     * Gerechnet wird auf dem Tageszinssatz, weil bcpow nur ganzzahlige
     * Exponenten kennt: Barwert = Summe CF / (1 + Tagessatz)^Tage. Der
     * Jahreswert ergibt sich aus (1 + Tagessatz)^365 - 1.
     *
     * @param  array<int, array{date: string, amount: string, label: string}>  $flows
     * @return array{0: ?string, 1: ?string} [Prozentsatz, Hinweis wenn nicht berechenbar]
     */
    public function internalRateOfReturn(array $flows): array
    {
        if (count($flows) < 2) {
            return [null, 'Für eine Effektivrendite sind mindestens eine Auszahlung und ein Rückfluss erforderlich.'];
        }

        $hasNegative = false;
        $hasPositive = false;
        foreach ($flows as $flow) {
            if (Money::isNegative($flow['amount'])) {
                $hasNegative = true;
            }
            if (Money::isPositive($flow['amount'])) {
                $hasPositive = true;
            }
        }
        if (! $hasNegative || ! $hasPositive) {
            return [null, 'Ohne Auszahlung und Rückfluss ist keine Effektivrendite ermittelbar.'];
        }

        $t0 = Carbon::parse($flows[0]['date']);
        $offsets = [];
        foreach ($flows as $flow) {
            $offsets[] = [
                (int) $t0->diffInDays(Carbon::parse($flow['date'])),
                Money::normalize($flow['amount'], Money::CALC_SCALE),
            ];
        }

        $low = self::DAILY_MIN;
        $high = self::DAILY_MAX;
        $npvLow = $this->netPresentValue($offsets, $low);
        $npvHigh = $this->netPresentValue($offsets, $high);

        if (bccomp($npvLow, '0', self::SCALE) === 0) {
            return $this->annualFromDaily($low);
        }
        if (bccomp($npvHigh, '0', self::SCALE) === 0) {
            return $this->annualFromDaily($high);
        }
        if ($this->sameSign($npvLow, $npvHigh)) {
            return [null, 'Im Bereich von -99 Prozent bis +1000 Prozent liegt keine eindeutige Lösung; die Effektivrendite ist nicht berechenbar.'];
        }

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $mid = bcdiv(bcadd($low, $high, self::SCALE), '2', self::SCALE);
            $npvMid = $this->netPresentValue($offsets, $mid);

            if (bccomp(Money::abs($npvMid, self::SCALE), self::TOLERANCE, self::SCALE) <= 0) {
                return $this->annualFromDaily($mid);
            }
            if ($this->sameSign($npvMid, $npvLow)) {
                $low = $mid;
                $npvLow = $npvMid;
            } else {
                $high = $mid;
            }
            if (bccomp(bcsub($high, $low, self::SCALE), self::TOLERANCE, self::SCALE) <= 0) {
                return $this->annualFromDaily($mid);
            }
        }

        return [null, 'Die Näherung ist nicht konvergiert; die Effektivrendite ist nicht berechenbar.'];
    }

    /**
     * Barwert der Zahlungsstroeme bei gegebenem Tageszinssatz.
     *
     * @param  array<int, array{0: int, 1: string}>  $offsets
     */
    protected function netPresentValue(array $offsets, string $dailyRate): string
    {
        $factorBase = bcadd('1', $dailyRate, self::SCALE);
        $npv = bcadd('0', '0', self::SCALE);

        foreach ($offsets as [$days, $amount]) {
            $discount = bcpow($factorBase, (string) $days, self::SCALE);
            if (bccomp($discount, '0', self::SCALE) === 0) {
                continue; // rechnerisch wertlos
            }
            $npv = bcadd($npv, bcdiv($amount, $discount, self::SCALE), self::SCALE);
        }

        return $npv;
    }

    /**
     * Jahreswert aus dem Tagessatz: (1 + Tagessatz)^365 - 1, als Prozentsatz
     * mit 4 Nachkommastellen. Ausserhalb des zulaessigen Bereichs wird nichts
     * ausgewiesen.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function annualFromDaily(string $dailyRate): array
    {
        $annualFactor = bcsub(bcpow(bcadd('1', $dailyRate, self::SCALE), '365', self::SCALE), '1', self::SCALE);

        if (bccomp($annualFactor, self::IRR_MIN, 6) < 0 || bccomp($annualFactor, self::IRR_MAX, 6) > 0) {
            return [null, 'Die ermittelte Effektivrendite liegt ausserhalb des zulaessigen Bereichs von -99 Prozent bis +1000 Prozent und wird nicht ausgewiesen.'];
        }

        return [Money::round(bcmul($annualFactor, '100', self::SCALE), 4), null];
    }

    protected function sameSign(string $a, string $b): bool
    {
        return bccomp($a, '0', self::SCALE) === bccomp($b, '0', self::SCALE);
    }
}
