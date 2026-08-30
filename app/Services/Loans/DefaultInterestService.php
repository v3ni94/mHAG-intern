<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\InterestMethod;
use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verzugszinsen (Masterprompt Abschnitt 44).
 *
 * ZWINGENDE FACHREGEL: Es wird KEIN gesetzlicher Verzugszinssatz angesetzt
 * und kein Verzugsbeginn unterstellt (Abschnitte 44, 133, 140). Ohne
 * aktivierte Verzugszinsen, ohne erfassten Satz und ohne erfassten
 * Verzugsbeginn berechnet und bucht dieser Service nichts; die Oberflaeche
 * weist auf die fehlenden fachlichen Vorgaben hin.
 *
 * Berechnung:
 * - taggenau auf den ueberfaelligen Betrag, Zeitraum [Verzugsbeginn, Stichtag],
 *   intern als Halboffenes Intervall [von, bis) mit bis = Stichtag + 1 Tag,
 *   damit der Stichtag selbst mitzaehlt;
 * - segmentiert nach jedem Tag, an dem sich der ueberfaellige Betrag aendert
 *   (Faelligkeiten und Zahlungsdaten der Planzeilen);
 * - Zinsmethode: default_interest_method, sonst die Methode des Darlehens;
 * - Grundlage: alle ueberfaelligen Positionen (overdue_total) oder nur
 *   ueberfaellige Tilgung (overdue_principal);
 * - Grundannahme Abschnitt 24: planned/assumed gilt als planmaessig erfuellt
 *   und ist NIE Verzug. Ueberfaellig sind nur Positionen mit erfasstem IST
 *   (nicht bezahlt, teilweise bezahlt) sowie verspaetet/bestaetigt bezahlte
 *   Positionen fuer die Zeit zwischen Faelligkeit und Zahlung.
 *
 * Buchung: BookingType::DefaultInterest, Wirkungsdatum = Stichtag,
 * Quelle = Darlehen. Eine erneute Berechnung storniert die eigene
 * Vorbuchung per Gegenbuchung (append-only, Abschnitt 49).
 */
class DefaultInterestService
{
    public const BASIS_OVERDUE_TOTAL = 'overdue_total';

    public const BASIS_OVERDUE_PRINCIPAL = 'overdue_principal';

    public const MODE_MANUAL = 'manual';

    public const MODE_AUTOMATIC = 'automatic';

    /** @var array<string, string> */
    public const BASIS_LABELS = [
        self::BASIS_OVERDUE_TOTAL => 'Alle überfälligen Positionen (Kapital, Zinsen, Gebühren)',
        self::BASIS_OVERDUE_PRINCIPAL => 'Nur überfällige Tilgung (Kapital)',
    ];

    /** @var array<string, string> */
    public const MODE_LABELS = [
        self::MODE_MANUAL => 'Manuell (nur auf ausdrückliche Anforderung)',
        self::MODE_AUTOMATIC => 'Automatisch (bei jeder Neuberechnung)',
    ];

    /**
     * Statuswerte mit erfasstem IST. Nur diese koennen Verzug begruenden;
     * planned/assumed gelten als planmaessig erfuellt (Abschnitt 24),
     * waived/cancelled begruenden keinen Verzug.
     */
    private const REAL_ACTUAL_STATUSES = [
        RepaymentItemStatus::Missed,
        RepaymentItemStatus::Partial,
        RepaymentItemStatus::Late,
        RepaymentItemStatus::Confirmed,
    ];

    public function __construct(protected InterestCalculationService $interest) {}

    /**
     * Fehlende fachliche Vorgaben in deutscher Klartextform.
     * Leeres Array = vollstaendig vorgegeben.
     *
     * @return array<int, string>
     */
    public function missingRequirements(Loan $loan): array
    {
        $missing = [];

        if (! $loan->default_interest_enabled) {
            $missing[] = 'Verzugszinsen sind für dieses Darlehen nicht aktiviert.';
        }
        if ($loan->default_interest_rate === null || Money::isZero(Money::normalize($loan->default_interest_rate, 6))) {
            $missing[] = 'Es ist kein Verzugszinssatz erfasst. Der Satz ist fachlich vorzugeben; das System setzt keinen gesetzlichen Satz an.';
        }
        if ($loan->default_interest_start === null) {
            $missing[] = 'Es ist kein Verzugsbeginn erfasst. Der Beginn des Verzugs ist fachlich vorzugeben.';
        }

        return $missing;
    }

    public function isConfigured(Loan $loan): bool
    {
        return $this->missingRequirements($loan) === [];
    }

    public function basisLabel(Loan $loan): string
    {
        return self::BASIS_LABELS[$loan->default_interest_basis] ?? self::BASIS_LABELS[self::BASIS_OVERDUE_TOTAL];
    }

    public function modeLabel(Loan $loan): string
    {
        return self::MODE_LABELS[$loan->default_interest_mode] ?? self::MODE_LABELS[self::MODE_MANUAL];
    }

    /** Zinsmethode der Verzugszinsen: eigene Vorgabe, sonst Methode des Darlehens. */
    public function method(Loan $loan): InterestMethod
    {
        if ($loan->default_interest_method instanceof InterestMethod) {
            return $loan->default_interest_method;
        }

        return InterestMethod::tryFrom((string) $loan->default_interest_method) ?? $loan->interest_method;
    }

    /**
     * Verzugszinsen zum Stichtag.
     *
     * @return array{
     *     configured: bool, missing: array<int, string>, amount: string,
     *     from: ?string, as_of: string, rate: ?string, method: ?string,
     *     basis: string, segments: array<int, array{from: string, to: string, days: int, base: string, amount: string}>
     * }
     */
    public function calculate(Loan $loan, ?CarbonInterface $asOf = null): array
    {
        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();
        $missing = $this->missingRequirements($loan);

        $result = [
            'configured' => $missing === [],
            'missing' => $missing,
            'amount' => '0.00',
            'from' => $loan->default_interest_start?->toDateString(),
            'as_of' => $asOfStr,
            'rate' => $loan->default_interest_rate !== null ? Money::normalize($loan->default_interest_rate, 6) : null,
            'method' => $missing === [] ? $this->method($loan)->value : null,
            'basis' => (string) ($loan->default_interest_basis ?: self::BASIS_OVERDUE_TOTAL),
            'segments' => [],
        ];

        if ($missing !== []) {
            return $result;
        }

        $from = $loan->default_interest_start->toDateString();
        if ($from > $asOfStr) {
            return $result; // Verzugsbeginn liegt nach dem Stichtag
        }

        $items = $this->relevantItems($loan, $result['basis']);
        $method = $this->method($loan);
        $rate = Money::normalize($loan->default_interest_rate, 6);

        // Segmentgrenzen: Verzugsbeginn, jede Faelligkeit, jedes Zahlungsdatum,
        // Ende = Stichtag + 1 Tag (der Stichtag zaehlt mit).
        $endExcl = Carbon::parse($asOfStr)->addDay()->toDateString();
        $points = [$from, $endExcl];
        foreach ($items as $item) {
            foreach ([$item->due_date?->toDateString(), $item->actual_date?->toDateString()] as $candidate) {
                if ($candidate !== null && $candidate > $from && $candidate < $endExcl) {
                    $points[] = $candidate;
                }
            }
        }
        $points = array_values(array_unique($points));
        sort($points);

        $total = bcadd('0', '0', Money::CALC_SCALE);
        for ($i = 0; $i < count($points) - 1; $i++) {
            $segStart = $points[$i];
            $segEnd = $points[$i + 1];

            $base = $this->overdueBaseAt($items, $segStart);
            if (! Money::isPositive($base)) {
                continue;
            }

            $segmentInterest = $this->interest->interestForPeriod(
                $base,
                $rate,
                $method,
                Carbon::parse($segStart),
                Carbon::parse($segEnd),
            );
            $total = bcadd($total, $segmentInterest, Money::CALC_SCALE);

            $result['segments'][] = [
                'from' => $segStart,
                'to' => $segEnd,
                'days' => (int) Carbon::parse($segStart)->diffInDays(Carbon::parse($segEnd)),
                'base' => $base,
                'amount' => Money::round($segmentInterest, 2),
            ];
        }

        $result['amount'] = Money::round($total, 2);

        return $result;
    }

    /**
     * Verzugszinsen berechnen und buchen. Ohne vollstaendige fachliche
     * Vorgabe wird NICHTS gebucht (Rueckgabe null). Eigene Vorbuchungen
     * werden zuvor per Gegenbuchung neutralisiert; Zahlungen auf
     * Verzugszinsen (Quelle: Zahlung) bleiben unberuehrt.
     */
    public function book(Loan $loan, ?CarbonInterface $asOf = null, ?User $user = null): ?LoanTransaction
    {
        $calculation = $this->calculate($loan, $asOf);
        if (! $calculation['configured']) {
            return null;
        }

        $amount = $calculation['amount'];
        $bookedOwn = $this->bookedOwnAmount($loan);

        if (Money::cmp($amount, $bookedOwn) === 0) {
            return null; // unveraendert: keine Buchung, kein Storno
        }

        return DB::transaction(function () use ($loan, $calculation, $amount, $user) {
            $this->reverseOwnBookings($loan, $user);

            if (! Money::isPositive($amount)) {
                return null;
            }

            $transaction = LoanTransaction::create([
                'loan_id' => $loan->id,
                'booking_type' => BookingType::DefaultInterest,
                'booking_date' => today()->toDateString(),
                'effective_date' => $calculation['as_of'],
                'amount' => $amount,
                'description' => sprintf(
                    'Verzugszinsen %s bis %s, Satz %s, Grundlage: %s',
                    format_date($calculation['from']),
                    format_date($calculation['as_of']),
                    format_percent($calculation['rate']),
                    $this->basisLabel($loan),
                ),
                'source_type' => $loan->getMorphClass(),
                'source_id' => $loan->id,
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);

            AuditService::log('loans.default_interest_booked', $loan, [], [
                'amount' => $amount,
                'from' => $calculation['from'],
                'as_of' => $calculation['as_of'],
                'rate' => $calculation['rate'],
                'method' => $calculation['method'],
                'basis' => $calculation['basis'],
            ]);

            return $transaction;
        });
    }

    /**
     * Ueberfaelliger Betrag zum Tag $dateStr.
     *
     * @param  \Illuminate\Support\Collection<int, RepaymentPlanItem>  $items
     */
    protected function overdueBaseAt($items, string $dateStr): string
    {
        $base = '0.00';

        foreach ($items as $item) {
            if ($item->due_date === null || $item->due_date->toDateString() > $dateStr) {
                continue;
            }
            $paidBy = $this->paidBy($item, $dateStr);
            $open = Money::sub($item->planned_amount, $paidBy);
            if (Money::isPositive($open)) {
                $base = Money::add($base, $open);
            }
        }

        return $base;
    }

    /** Bis zum Tag $dateStr geleisteter IST-Betrag einer Planzeile. */
    protected function paidBy(RepaymentPlanItem $item, string $dateStr): string
    {
        $paidOn = ($item->actual_date ?? $item->due_date)?->toDateString();
        if ($paidOn === null || $paidOn > $dateStr) {
            return '0.00';
        }

        return Money::normalize($item->actual_amount);
    }

    /**
     * Planzeilen, die Verzug begruenden koennen.
     *
     * @return \Illuminate\Support\Collection<int, RepaymentPlanItem>
     */
    protected function relevantItems(Loan $loan, string $basis)
    {
        return $loan->repaymentPlanItems()
            ->whereIn('status', array_map(fn (RepaymentItemStatus $s) => $s->value, self::REAL_ACTUAL_STATUSES))
            ->when(
                $basis === self::BASIS_OVERDUE_PRINCIPAL,
                fn ($q) => $q->where('item_type', \App\Enums\RepaymentItemType::Principal->value),
            )
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /** Netto bereits gebuchte eigene Verzugszinsen (ohne Zahlungen darauf). */
    protected function bookedOwnAmount(Loan $loan): string
    {
        $net = '0.00';
        foreach ($this->ownTransactions($loan) as $tx) {
            $net = Money::add($net, $tx->amount);
        }

        return $net;
    }

    /** @return \Illuminate\Support\Collection<int, LoanTransaction> */
    protected function ownTransactions(Loan $loan)
    {
        return LoanTransaction::query()
            ->where('loan_id', $loan->id)
            ->where('source_type', $loan->getMorphClass())
            ->where('source_id', $loan->id)
            ->whereIn('booking_type', [BookingType::DefaultInterest->value, BookingType::Cancellation->value])
            ->orderBy('id')
            ->get();
    }

    /**
     * Eigene Verzugszins-Buchungen per Gegenbuchung neutralisieren
     * (niemals loeschen, Abschnitt 49). Wirkungsdatum der Gegenbuchung
     * = Wirkungsdatum der Originalbuchung.
     */
    protected function reverseOwnBookings(Loan $loan, ?User $user): void
    {
        $own = $this->ownTransactions($loan);
        $reversedIds = $own->pluck('reversal_of')->filter()->all();

        foreach ($own as $tx) {
            if ($tx->booking_type !== BookingType::DefaultInterest || in_array($tx->id, $reversedIds, true)) {
                continue;
            }
            LoanTransaction::create([
                'loan_id' => $loan->id,
                'booking_type' => BookingType::Cancellation,
                'booking_date' => today()->toDateString(),
                'effective_date' => $tx->effective_date->toDateString(),
                'amount' => Money::negate($tx->amount),
                'description' => 'Storno der zuvor berechneten Verzugszinsen (Neuberechnung)',
                'source_type' => $loan->getMorphClass(),
                'source_id' => $loan->id,
                'reversal_of' => $tx->id,
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);
        }
    }
}
