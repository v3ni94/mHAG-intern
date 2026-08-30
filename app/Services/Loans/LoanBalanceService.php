<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\Loan;
use App\Models\RepaymentPlanItem;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Salden und Forderungsaufstellung eines Darlehens, stichtagsfaehig
 * (Masterprompt Abschnitte 48, 50, 51).
 *
 * Grundsaetze:
 * - Kapitalwerte werden aus loan_transactions berechnet, nie doppelt gefuehrt.
 * - Zins-/Gebuehrenwerte kommen aus dem Zahlungsplan (SOLL/IST je Zeile).
 * - Systemseitig angenommene Werte (assumed) werden IMMER getrennt ausgewiesen
 *   und gelten fuer die Forderungshoehe als erfuellt (Abschnitt 24);
 *   ueberfaellig zaehlen nur missed/partial.
 */
class LoanBalanceService
{
    private const CONFIRMED_STATUSES = [
        RepaymentItemStatus::Confirmed,
        RepaymentItemStatus::Partial,
        RepaymentItemStatus::Late,
    ];

    /**
     * Reine Annahme-Zustaende (Abschnitt 24): planned zaehlt fuer Stichtage
     * mit due_date <= asOf ebenfalls als planmaessig erfuellt, unabhaengig
     * davon, ob rollForwardAssumed bereits gelaufen ist.
     */
    private const ASSUMED_STATUSES = [
        RepaymentItemStatus::Planned,
        RepaymentItemStatus::Assumed,
    ];

    /**
     * Alle Werte als Dezimalstrings (2 NK); asOf = null bedeutet heute.
     *
     * Keys: disbursed, repaid, capitalized, principal_outstanding,
     * interest_charged, interest_confirmed, interest_assumed, interest_open,
     * interest_capitalized, fees_charged,
     * fees_paid, fees_confirmed, fees_assumed, fees_open,
     * default_interest, account_balance,
     * payments_received, total_receivable, overdue_amount, next_due_date,
     * next_due_amount.
     */
    public function balances(Loan $loan, ?CarbonInterface $asOf = null): array
    {
        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();

        $capital = $this->capitalComponents($loan, $asOfStr);

        $interestCharged = '0.00';
        $interestConfirmed = '0.00';
        $interestAssumed = '0.00';
        $interestOpen = '0.00';
        $interestCapitalized = '0.00';
        $feesCharged = '0.00';
        $feesPaid = '0.00';
        $feesConfirmed = '0.00';
        $feesAssumed = '0.00';
        $feesOpen = '0.00';
        $overdue = '0.00';

        $items = $loan->repaymentPlanItems()->orderBy('due_date')->orderBy('id')->get()
            ->filter(fn (RepaymentPlanItem $i) => $i->status !== RepaymentItemStatus::Cancelled);

        foreach ($items as $item) {
            if ($item->due_date->toDateString() > $asOfStr) {
                continue;
            }

            $planned = Money::normalize($item->planned_amount);
            $paidEffective = $this->effectiveActualAsOf($item, $asOfStr);
            $open = Money::max(Money::sub($planned, $paidEffective), '0.00');

            if (in_array($item->status, [RepaymentItemStatus::Missed, RepaymentItemStatus::Partial], true)) {
                $overdue = Money::add($overdue, $open);
            }

            if ($item->item_type === RepaymentItemType::Interest) {
                $interestCharged = Money::add($interestCharged, $planned);
                if ($item->status === RepaymentItemStatus::Capitalized) {
                    // Dem Kapital zugeschrieben: keine offene Zinsforderung,
                    // der Betrag steckt ueber die Buchung im Kapital.
                    $interestCapitalized = Money::add($interestCapitalized, $planned);
                } else {
                    if (in_array($item->status, self::ASSUMED_STATUSES, true)) {
                        $interestAssumed = Money::add($interestAssumed, $paidEffective);
                    } elseif (in_array($item->status, self::CONFIRMED_STATUSES, true)) {
                        $interestConfirmed = Money::add($interestConfirmed, $paidEffective);
                    }
                    if ($item->status !== RepaymentItemStatus::Waived) {
                        $interestOpen = Money::add($interestOpen, $open);
                    }
                }
            } elseif ($item->item_type === RepaymentItemType::Fee) {
                $feesCharged = Money::add($feesCharged, $planned);
                $feesPaid = Money::add($feesPaid, $paidEffective);
                // Bestaetigte und systemseitig angenommene Gebuehrenzahlungen
                // getrennt fuehren (Abschnitt 24), wie bei den Zinsen.
                if (in_array($item->status, self::ASSUMED_STATUSES, true)) {
                    $feesAssumed = Money::add($feesAssumed, $paidEffective);
                } elseif (in_array($item->status, self::CONFIRMED_STATUSES, true)) {
                    $feesConfirmed = Money::add($feesConfirmed, $paidEffective);
                }
                if ($item->status !== RepaymentItemStatus::Waived) {
                    $feesOpen = Money::add($feesOpen, $open);
                }
            }
            // Tilgungszeilen: Kapitalseite kommt ausschliesslich aus loan_transactions.
        }

        $paymentsReceived = Money::sum(
            $loan->payments()
                ->where('status', 'recorded')
                ->where('direction', 'incoming')
                ->whereDate('payment_date', '<=', $asOfStr)
                ->pluck('amount'),
        );

        [$nextDueDate, $nextDueAmount] = $this->nextDue($loan, $asOfStr);
        $accountBalance = $this->accountBalance($loan, Carbon::parse($asOfStr));

        $totalReceivable = Money::add(
            Money::add($capital['principal_outstanding'], $interestOpen),
            Money::add($feesOpen, Money::max($capital['default_interest'], '0.00')),
        );

        return [
            'disbursed' => $capital['disbursed'],
            'repaid' => $capital['repaid'],
            'capitalized' => $capital['capitalized'],
            'written_off' => $capital['written_off'],
            'principal_outstanding' => $capital['principal_outstanding'],
            'interest_charged' => $interestCharged,
            'interest_confirmed' => $interestConfirmed,
            'interest_assumed' => $interestAssumed,
            'interest_open' => $interestOpen,
            'interest_capitalized' => $interestCapitalized,
            'fees_charged' => $feesCharged,
            'fees_paid' => $feesPaid,
            'fees_confirmed' => $feesConfirmed,
            'fees_assumed' => $feesAssumed,
            'fees_open' => $feesOpen,
            'default_interest' => $capital['default_interest'],
            'account_balance' => $accountBalance,
            'payments_received' => $paymentsReceived,
            'total_receivable' => $totalReceivable,
            'overdue_amount' => $overdue,
            'next_due_date' => $nextDueDate,
            'next_due_amount' => $nextDueAmount,
        ];
    }

    /**
     * Kontostand des Darlehenskontos zum Stichtag: die Summe aller Buchungen
     * mit Wirkungsdatum bis einschliesslich Stichtag (Forderungssicht, ein
     * positiver Wert ist eine Forderung des Darlehensgebers).
     *
     * Abgrenzung zur Gesamtforderung: der Kontostand enthaelt ausschliesslich
     * das, was tatsaechlich gebucht ist. Die Gesamtforderung enthaelt
     * zusaetzlich die bis zum Stichtag entstandenen, aber noch nicht
     * gebuchten Soll-Positionen aus dem Zahlungsplan (offene Zinsen und
     * Gebuehren). Beide Zahlen sind deshalb regelmaessig verschieden.
     */
    public function accountBalance(Loan $loan, ?CarbonInterface $asOf = null): string
    {
        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();

        return Money::sum(
            $loan->transactions()
                ->whereDate('effective_date', '<=', $asOfStr)
                ->pluck('amount'),
        );
    }

    /**
     * Kontostaende mehrerer Darlehen zum Stichtag in EINER Abfrage. Fuer
     * Listen: keine Abfrage je Zeile. Summiert wird mit BCMath in PHP, nicht
     * mit SUM() in der Datenbank, weil SQLite dabei auf Gleitkommazahlen
     * ausweicht (eiserne Regel 1: nie float bei Geld).
     *
     * @param  array<int, int>  $loanIds
     * @return array<int, string>  Kontostand je Darlehens-ID
     */
    public function accountBalancesFor(array $loanIds, ?CarbonInterface $asOf = null): array
    {
        if ($loanIds === []) {
            return [];
        }

        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();

        $balances = array_fill_keys($loanIds, '0.00');
        $rows = \App\Models\LoanTransaction::query()
            ->whereIn('loan_id', $loanIds)
            ->whereDate('effective_date', '<=', $asOfStr)
            ->get(['loan_id', 'amount']);

        foreach ($rows as $row) {
            $balances[$row->loan_id] = Money::add($balances[$row->loan_id] ?? '0.00', $row->amount);
        }

        return $balances;
    }

    /**
     * Forderungsaufstellung (Abschnitt 51):
     * Kapital + Vertragszinsen + Verzugszinsen + Gebuehren - Zahlungen = Gesamtforderung.
     * Rueckgabe: ['as_of' => 'Y-m-d', 'rows' => [['label','amount','sign'], ...], 'total' => string]
     */
    public function statementRows(Loan $loan, CarbonInterface $asOf): array
    {
        $asOfStr = Carbon::parse($asOf->toDateString())->toDateString();
        $b = $this->balances($loan, $asOf);
        $capital = $this->capitalComponents($loan, $asOfStr);

        // Kapitalisierte Zinsen sind in interest_charged enthalten, aber weder
        // bezahlt noch offen: sie stecken im Kapital. Sie sind deshalb aus dem
        // rechnerischen Zahlungsbetrag herauszunehmen und gesondert auszuweisen.
        $interestPaidEffective = Money::sub(
            Money::sub($b['interest_charged'], $b['interest_open']),
            $b['interest_capitalized'],
        );
        $feesPaidEffective = Money::sub($b['fees_charged'], $b['fees_open']);

        $rows = [];
        $rows[] = ['label' => 'Ausgezahltes Kapital', 'amount' => $b['disbursed'], 'sign' => '+'];
        if (Money::isPositive($b['capitalized'])) {
            $rows[] = ['label' => 'Zugeschriebene Zinsen im valutierten Betrag', 'amount' => $b['capitalized'], 'sign' => '+'];
        }
        $rows[] = ['label' => 'Vertragszinsen bis '.Carbon::parse($asOfStr)->format('d.m.Y'), 'amount' => $b['interest_charged'], 'sign' => '+'];
        // Verzugszinsen nur ausweisen, wenn tatsaechlich berechnet und gebucht
        // (Abschnitt 143: keine Schein-Positionen). Eine aktivierte, aber
        // fachlich nicht vorgegebene Verzugszinsregelung erzeugt KEINE Zeile
        // mit 0,00 EUR; der Hinweis dazu steht im PDF.
        if (Money::isPositive($b['default_interest'])) {
            $rows[] = ['label' => 'Verzugszinsen', 'amount' => $b['default_interest'], 'sign' => '+'];
        }
        if (! Money::isZero($b['fees_charged'])) {
            $rows[] = ['label' => 'Gebühren', 'amount' => $b['fees_charged'], 'sign' => '+'];
        }
        $rows[] = ['label' => 'Tilgungen', 'amount' => $b['repaid'], 'sign' => '-'];
        $rows[] = ['label' => 'Zinszahlungen (inkl. systemseitig angenommener Zahlungen)', 'amount' => $interestPaidEffective, 'sign' => '-'];
        if (Money::isPositive($b['interest_capitalized'])) {
            $rows[] = [
                'label' => 'Kapitalisierte Zinsen, bereits im valutierten Betrag enthalten',
                'amount' => $b['interest_capitalized'],
                'sign' => '-',
            ];
        }
        if (! Money::isZero($feesPaidEffective)) {
            $rows[] = ['label' => 'Gebührenzahlungen (inkl. systemseitig angenommener Zahlungen)', 'amount' => $feesPaidEffective, 'sign' => '-'];
        }
        if (! Money::isZero($capital['written_off'])) {
            $rows[] = ['label' => 'Abschreibungen', 'amount' => $capital['written_off'], 'sign' => '-'];
        }

        return [
            'as_of' => $asOfStr,
            'rows' => $rows,
            'total' => $b['total_receivable'],
        ];
    }

    /**
     * Kapitalkomponenten aus loan_transactions bis zum Stichtag (inklusive).
     * Stornos/Korrekturen wirken ueber reversal_of auf die stornierte Buchungsart.
     *
     * @return array{disbursed: string, repaid: string, written_off: string, capitalized: string, principal_outstanding: string, default_interest: string}
     */
    protected function capitalComponents(Loan $loan, string $asOfStr): array
    {
        $disbursed = '0.00';
        $repaid = '0.00';
        $writtenOff = '0.00';
        $capitalized = '0.00';
        $defaultInterest = '0.00';

        $transactions = $loan->transactions()->with('reversalOf')->get();
        foreach ($transactions as $tx) {
            if ($tx->effective_date->toDateString() > $asOfStr) {
                continue;
            }

            $base = $tx->booking_type;
            if (in_array($base, [BookingType::Cancellation, BookingType::Correction], true) && $tx->reversalOf) {
                $base = $tx->reversalOf->booking_type;
            }

            $amount = Money::normalize($tx->amount);
            match ($base) {
                BookingType::Disbursement => $disbursed = Money::add($disbursed, $amount),
                BookingType::Repayment => $repaid = Money::sub($repaid, $amount),
                BookingType::WriteOff => $writtenOff = Money::sub($writtenOff, $amount),
                BookingType::InterestCapitalization => $capitalized = Money::add($capitalized, $amount),
                BookingType::DefaultInterest => $defaultInterest = Money::add($defaultInterest, $amount),
                default => null,
            };
        }

        return [
            'disbursed' => $disbursed,
            'repaid' => $repaid,
            'written_off' => $writtenOff,
            'capitalized' => $capitalized,
            // Zugeschriebene Zinsen erhoehen das valutierte Kapital.
            'principal_outstanding' => Money::sub(
                Money::sub(Money::add($disbursed, $capitalized), $repaid),
                $writtenOff,
            ),
            'default_interest' => $defaultInterest,
        ];
    }

    /**
     * IST-Wert einer Planzeile zum Stichtag:
     * - assumed gilt als planmaessig erfuellt (Abschnitt 24), sobald faellig;
     * - echte IST-Werte zaehlen erst ab ihrem Zahlungs-/Faelligkeitsdatum
     *   (verspaetete Zahlungen sind am frueheren Stichtag noch offen).
     */
    protected function effectiveActualAsOf(RepaymentPlanItem $item, string $asOfStr): string
    {
        if (in_array($item->status, self::ASSUMED_STATUSES, true)) {
            return Money::normalize($item->planned_amount);
        }
        if (in_array($item->status, self::CONFIRMED_STATUSES, true)) {
            $paidOn = ($item->actual_date ?? $item->due_date)->toDateString();

            return $paidOn <= $asOfStr ? Money::normalize($item->actual_amount) : '0.00';
        }

        return '0.00';
    }

    /** @return array{0: ?string, 1: string} */
    protected function nextDue(Loan $loan, string $asOfStr): array
    {
        $upcoming = $loan->repaymentPlanItems()
            ->whereDate('due_date', '>', $asOfStr)
            ->whereIn('status', [RepaymentItemStatus::Planned->value, RepaymentItemStatus::Assumed->value])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $first = $upcoming->first();
        if (! $first) {
            return [null, '0.00'];
        }

        $dueDate = $first->due_date->toDateString();
        $amount = Money::sum(
            $upcoming->filter(fn (RepaymentPlanItem $i) => $i->due_date->toDateString() === $dueDate)
                ->map(fn (RepaymentPlanItem $i) => $i->planned_amount),
        );

        return [$dueDate, $amount];
    }
}
