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
     * Keys: disbursed, repaid, principal_outstanding, interest_charged,
     * interest_confirmed, interest_assumed, interest_open, fees_charged,
     * fees_paid, fees_open, default_interest, payments_received,
     * total_receivable, overdue_amount, next_due_date, next_due_amount.
     */
    public function balances(Loan $loan, ?CarbonInterface $asOf = null): array
    {
        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();

        $capital = $this->capitalComponents($loan, $asOfStr);

        $interestCharged = '0.00';
        $interestConfirmed = '0.00';
        $interestAssumed = '0.00';
        $interestOpen = '0.00';
        $feesCharged = '0.00';
        $feesPaid = '0.00';
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
                if (in_array($item->status, self::ASSUMED_STATUSES, true)) {
                    $interestAssumed = Money::add($interestAssumed, $paidEffective);
                } elseif (in_array($item->status, self::CONFIRMED_STATUSES, true)) {
                    $interestConfirmed = Money::add($interestConfirmed, $paidEffective);
                }
                if ($item->status !== RepaymentItemStatus::Waived) {
                    $interestOpen = Money::add($interestOpen, $open);
                }
            } elseif ($item->item_type === RepaymentItemType::Fee) {
                $feesCharged = Money::add($feesCharged, $planned);
                $feesPaid = Money::add($feesPaid, $paidEffective);
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

        $totalReceivable = Money::add(
            Money::add($capital['principal_outstanding'], $interestOpen),
            Money::add($feesOpen, Money::max($capital['default_interest'], '0.00')),
        );

        return [
            'disbursed' => $capital['disbursed'],
            'repaid' => $capital['repaid'],
            'principal_outstanding' => $capital['principal_outstanding'],
            'interest_charged' => $interestCharged,
            'interest_confirmed' => $interestConfirmed,
            'interest_assumed' => $interestAssumed,
            'interest_open' => $interestOpen,
            'fees_charged' => $feesCharged,
            'fees_paid' => $feesPaid,
            'fees_open' => $feesOpen,
            'default_interest' => $capital['default_interest'],
            'payments_received' => $paymentsReceived,
            'total_receivable' => $totalReceivable,
            'overdue_amount' => $overdue,
            'next_due_date' => $nextDueDate,
            'next_due_amount' => $nextDueAmount,
        ];
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

        $interestPaidEffective = Money::sub($b['interest_charged'], $b['interest_open']);
        $feesPaidEffective = Money::sub($b['fees_charged'], $b['fees_open']);

        $rows = [];
        $rows[] = ['label' => 'Ausgezahltes Kapital', 'amount' => $b['disbursed'], 'sign' => '+'];
        $rows[] = ['label' => 'Vertragszinsen bis '.Carbon::parse($asOfStr)->format('d.m.Y'), 'amount' => $b['interest_charged'], 'sign' => '+'];
        if (! Money::isZero($b['default_interest']) || $loan->default_interest_enabled) {
            $rows[] = ['label' => 'Verzugszinsen', 'amount' => Money::max($b['default_interest'], '0.00'), 'sign' => '+'];
        }
        if (! Money::isZero($b['fees_charged'])) {
            $rows[] = ['label' => 'Gebühren', 'amount' => $b['fees_charged'], 'sign' => '+'];
        }
        $rows[] = ['label' => 'Tilgungen', 'amount' => $b['repaid'], 'sign' => '-'];
        $rows[] = ['label' => 'Zinszahlungen (inkl. systemseitig angenommener Zahlungen)', 'amount' => $interestPaidEffective, 'sign' => '-'];
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
     * @return array{disbursed: string, repaid: string, written_off: string, principal_outstanding: string, default_interest: string}
     */
    protected function capitalComponents(Loan $loan, string $asOfStr): array
    {
        $disbursed = '0.00';
        $repaid = '0.00';
        $writtenOff = '0.00';
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
                BookingType::DefaultInterest => $defaultInterest = Money::add($defaultInterest, $amount),
                default => null,
            };
        }

        return [
            'disbursed' => $disbursed,
            'repaid' => $repaid,
            'written_off' => $writtenOff,
            'principal_outstanding' => Money::sub(Money::sub($disbursed, $repaid), $writtenOff),
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
