<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\LoanTransaction;
use App\Models\PaymentAllocation;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * IST-Erfassung ueber den Zahlungsplan (Masterprompt Abschnitte 23, 26-29, 48).
 *
 * Wird eine Planzeile auf einen erfuellten Status gesetzt (bestaetigt,
 * teilweise, verspaetet), muss die Wirkung im Darlehenskonto entstehen:
 *   item_type principal -> BookingType::Repayment      (Kapital sinkt)
 *   item_type interest  -> BookingType::InterestPayment
 *   item_type fee       -> BookingType::FeePayment
 * Betrag NEGATIV (Forderungssicht), Wirkungsdatum = actual_date, sonst due_date.
 *
 * Grundsaetze:
 * - Keine Doppelbuchung: Betraege, die bereits ueber eine Zahlung mit
 *   Verrechnung auf dieselbe Planzeile gebucht wurden
 *   (payment_allocations.repayment_plan_item_id), werden abgezogen.
 * - Append-only (Abschnitt 49): wird der Status korrigiert oder
 *   zurueckgenommen, wird die eigene Vorbuchung per Gegenbuchung
 *   (reversal_of) neutralisiert und, falls noetig, neu gebucht.
 * - Idempotent: stimmt die Wirkung bereits, entsteht keine Buchung.
 */
class ScheduleActualService
{
    /** Status mit erfuellter Zahlung (IST wirkt im Darlehenskonto). */
    private const FULFILLED_STATUSES = [
        RepaymentItemStatus::Confirmed,
        RepaymentItemStatus::Partial,
        RepaymentItemStatus::Late,
    ];

    /** Buchungsarten, die aus einer Planzeile heraus entstehen koennen. */
    private const ITEM_BOOKING_TYPES = [
        BookingType::Repayment,
        BookingType::InterestPayment,
        BookingType::FeePayment,
    ];

    /**
     * Wirkung einer Planzeile im Darlehenskonto herstellen.
     * Rueckgabe: die neu erzeugte Buchung oder null (keine Aenderung).
     */
    public function reconcile(RepaymentPlanItem $item, ?User $user = null, ?string $reason = null): ?LoanTransaction
    {
        $target = $this->targetAmount($item);
        $booked = $this->bookedFromItem($item);

        if (Money::cmp($target, $booked) === 0) {
            return null;
        }

        return DB::transaction(function () use ($item, $target, $user, $reason) {
            $this->reverseOwnBookings($item, $user, $reason);

            if (! Money::isPositive($target)) {
                return null;
            }

            $effectiveDate = ($item->actual_date ?? $item->due_date)->toDateString();

            return LoanTransaction::create([
                'loan_id' => $item->loan_id,
                'booking_type' => $this->bookingTypeFor($item->item_type),
                'booking_date' => today()->toDateString(),
                'effective_date' => $effectiveDate,
                'amount' => Money::negate($target),
                'description' => sprintf(
                    '%s aus Zahlungsplan-Position vom %s (%s)',
                    $item->item_type->label(),
                    format_date($item->due_date),
                    $item->status->label(),
                ),
                'source_type' => $item->getMorphClass(),
                'source_id' => $item->id,
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Aus der Planzeile selbst zu buchender Betrag: erfuellter IST-Betrag
     * abzueglich der Betraege, die bereits ueber Zahlungen auf diese Zeile
     * verrechnet und dort gebucht wurden.
     */
    public function targetAmount(RepaymentPlanItem $item): string
    {
        if (! in_array($item->status, self::FULFILLED_STATUSES, true)) {
            return '0.00';
        }

        $actual = Money::normalize($item->actual_amount);
        $fromPayments = $this->allocatedFromPayments($item);

        return Money::max(Money::sub($actual, $fromPayments), '0.00');
    }

    /** Bereits ueber (nicht stornierte) Zahlungen auf diese Zeile verrechnet. */
    protected function allocatedFromPayments(RepaymentPlanItem $item): string
    {
        return Money::sum(
            PaymentAllocation::query()
                ->where('repayment_plan_item_id', $item->id)
                ->whereHas('payment', fn ($q) => $q->where('status', 'recorded'))
                ->pluck('amount'),
        );
    }

    /**
     * Netto bereits aus dieser Planzeile gebuchte Wirkung (positiver Betrag);
     * Gegenbuchungen sind darin bereits verrechnet.
     */
    protected function bookedFromItem(RepaymentPlanItem $item): string
    {
        $net = '0.00';
        foreach ($this->ownTransactions($item) as $tx) {
            $net = Money::add($net, $tx->amount);
        }

        return Money::negate($net);
    }

    /** @return Collection<int, LoanTransaction> */
    protected function ownTransactions(RepaymentPlanItem $item)
    {
        return LoanTransaction::query()
            ->where('loan_id', $item->loan_id)
            ->where('source_type', $item->getMorphClass())
            ->where('source_id', $item->id)
            ->orderBy('id')
            ->get();
    }

    /** Gegenbuchung aller eigenen, noch nicht stornierten Buchungen. */
    protected function reverseOwnBookings(RepaymentPlanItem $item, ?User $user, ?string $reason): void
    {
        $own = $this->ownTransactions($item);
        $reversedIds = $own->pluck('reversal_of')->filter()->all();

        foreach ($own as $tx) {
            if (! in_array($tx->booking_type, self::ITEM_BOOKING_TYPES, true) || in_array($tx->id, $reversedIds, true)) {
                continue;
            }
            LoanTransaction::create([
                'loan_id' => $tx->loan_id,
                'booking_type' => BookingType::Cancellation,
                'booking_date' => today()->toDateString(),
                'effective_date' => $tx->effective_date->toDateString(),
                'amount' => Money::negate($tx->amount),
                'description' => 'Storno der Buchung aus der Zahlungsplan-Position'.($reason ? ': '.$reason : ''),
                'source_type' => $item->getMorphClass(),
                'source_id' => $item->id,
                'reversal_of' => $tx->id,
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);
        }
    }

    protected function bookingTypeFor(RepaymentItemType $type): BookingType
    {
        return match ($type) {
            RepaymentItemType::Principal => BookingType::Repayment,
            RepaymentItemType::Interest => BookingType::InterestPayment,
            RepaymentItemType::Fee => BookingType::FeePayment,
        };
    }
}
