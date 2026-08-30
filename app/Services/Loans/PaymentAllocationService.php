<?php

namespace App\Services\Loans;

use App\Enums\AllocationBucket;
use App\Enums\BookingType;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentPlanItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Verrechnung von Zahlungseingaengen (Masterprompt Abschnitte 46-47).
 *
 * Reihenfolge aus Setting('loans','allocation_order'), Default:
 * Kosten, Gebuehren, Verzugszinsen, Vertragszinsen, Kapital.
 * Befuellt offene repayment_plan_items chronologisch (aelteste zuerst),
 * schreibt payment_allocations und loan_transactions (Betraege NEGATIV,
 * Forderung sinkt). Rest fliesst als Tilgung ins Kapital; Ueberzahlung
 * ohne Ziel landet im Bucket "other".
 *
 * Hinweis: Nach einer Kapitalverrechnung muss der Aufrufer
 * LoanRecalculationService::recalculate anstossen, damit kuenftige
 * Zins-SOLL-Zeilen dem neuen Kapitalverlauf folgen.
 */
class PaymentAllocationService
{
    public const DEFAULT_ORDER = ['costs', 'fees', 'default_interest', 'interest', 'principal'];

    /** Fuer die Verrechnung "offen": planned/assumed ohne echtes IST, missed, partial. */
    private const FILLABLE_STATUSES = [
        RepaymentItemStatus::Planned,
        RepaymentItemStatus::Assumed,
        RepaymentItemStatus::Missed,
        RepaymentItemStatus::Partial,
    ];

    private const REAL_ACTUAL_STATUSES = [
        RepaymentItemStatus::Confirmed,
        RepaymentItemStatus::Partial,
        RepaymentItemStatus::Late,
    ];

    public function __construct(
        protected InterestCalculationService $interest,
        protected ScheduleActualService $scheduleActuals,
    ) {}

    /**
     * Verrechnet eine Zahlung. manualBuckets erlaubt eine manuelle Aufteilung
     * ['interest' => '300.00', ...]; ohne Vorgabe gilt die konfigurierte
     * Reihenfolge. Rueckgabe: bucket => verrechneter Betrag.
     */
    public function allocate(Payment $payment, ?array $manualBuckets = null): array
    {
        if ($payment->status === 'cancelled') {
            throw new \InvalidArgumentException('Stornierte Zahlungen können nicht verrechnet werden.');
        }
        if (! Money::isPositive(Money::normalize($payment->amount))) {
            throw new \InvalidArgumentException('Zahlungsbetrag muss größer 0 sein.');
        }

        return DB::transaction(function () use ($payment, $manualBuckets) {
            $loan = $payment->loan()->firstOrFail();
            $result = [];
            $remaining = Money::normalize($payment->amount);

            if ($manualBuckets !== null) {
                $remaining = $this->allocateManual($payment, $loan, $manualBuckets, $remaining, $result);
            } else {
                $remaining = $this->allocateByOrder($payment, $loan, $remaining, $result);
            }

            // Rest: Tilgung auf das offene Kapital, darueber hinaus Bucket "other".
            if (Money::isPositive($remaining)) {
                $capital = Money::max($this->interest->capitalAt($loan, $payment->payment_date), '0.00');
                $toPrincipal = Money::min($remaining, $capital);
                if (Money::isPositive($toPrincipal)) {
                    $this->bookAllocation($payment, $loan, AllocationBucket::Principal, $toPrincipal, null, $result);
                    $remaining = Money::sub($remaining, $toPrincipal);
                }
            }
            if (Money::isPositive($remaining)) {
                $this->bookAllocation($payment, $loan, AllocationBucket::Other, $remaining, null, $result);
            }

            AuditService::log('payments.allocated', $payment, [], $result, ['loan_id' => $loan->id]);

            return $result;
        });
    }

    /**
     * Storno einer Zahlung (Abschnitt 49): niemals loeschen, sondern
     * Gegenbuchungen im Darlehenskonto und Korrektur der betroffenen
     * Planzeilen. Der Aufrufer stoesst danach die Neuberechnung an.
     */
    public function cancel(Payment $payment, ?string $reason = null, ?User $user = null): void
    {
        if ($payment->status === 'cancelled') {
            throw new \InvalidArgumentException('Die Zahlung ist bereits storniert.');
        }

        DB::transaction(function () use ($payment, $reason, $user) {
            $payment->update([
                'status' => 'cancelled',
                'cancelled_by' => $user?->id ?? auth()->id(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // Gegenbuchungen fuer alle eigenen, noch nicht stornierten Buchungen.
            $own = LoanTransaction::query()
                ->where('source_type', $payment->getMorphClass())
                ->where('source_id', $payment->id)
                ->orderBy('id')
                ->get();
            $reversedIds = $own->pluck('reversal_of')->filter()->all();
            foreach ($own as $tx) {
                if ($tx->booking_type === BookingType::Cancellation || in_array($tx->id, $reversedIds, true)) {
                    continue;
                }
                LoanTransaction::create([
                    'loan_id' => $tx->loan_id,
                    'booking_type' => BookingType::Cancellation,
                    'booking_date' => today()->toDateString(),
                    'effective_date' => $tx->effective_date->toDateString(),
                    'amount' => Money::negate($tx->amount),
                    'description' => 'Storno Zahlung'.($reason ? ': '.$reason : ''),
                    'source_type' => $payment->getMorphClass(),
                    'source_id' => $payment->id,
                    'reversal_of' => $tx->id,
                    'created_by' => $user?->id ?? auth()->id(),
                    'created_at' => now(),
                ]);
            }

            // Betroffene Planzeilen aus den verbleibenden Zahlungen neu ableiten.
            $itemIds = $payment->allocations()->whereNotNull('repayment_plan_item_id')->pluck('repayment_plan_item_id')->unique();
            foreach (RepaymentPlanItem::query()->whereIn('id', $itemIds)->get() as $item) {
                $this->rebuildItemActuals($item, $reason);
                // Wirkung im Darlehenskonto an den neuen IST-Stand angleichen:
                // eine zuvor aus der Planzeile selbst gebuchte Wirkung wird
                // per Gegenbuchung aufgehoben (Abschnitt 49).
                $this->scheduleActuals->reconcile($item, $user, 'Zahlung storniert');
            }

            AuditService::log('payments.cancelled', $payment, [], [], ['reason' => $reason]);
        });
    }

    // ------------------------------------------------------------------
    // interne Verrechnung
    // ------------------------------------------------------------------

    protected function allocateByOrder(Payment $payment, Loan $loan, string $remaining, array &$result): string
    {
        $order = Setting::get('loans', 'allocation_order', self::DEFAULT_ORDER);

        foreach ((array) $order as $bucketName) {
            if (! Money::isPositive($remaining)) {
                break;
            }
            $bucket = AllocationBucket::tryFrom((string) $bucketName);
            if (! $bucket) {
                continue;
            }
            $open = $this->openAmountForBucket($payment, $loan, $bucket);
            $take = Money::min($remaining, $open);
            if (! Money::isPositive($take)) {
                continue;
            }
            $this->allocateToBucket($payment, $loan, $bucket, $take, $result);
            $remaining = Money::sub($remaining, $take);
        }

        return $remaining;
    }

    protected function allocateManual(Payment $payment, Loan $loan, array $manualBuckets, string $remaining, array &$result): string
    {
        $total = Money::sum(array_values($manualBuckets));
        if (Money::cmp($total, $remaining) > 0) {
            throw new \InvalidArgumentException('Die manuelle Aufteilung übersteigt den Zahlungsbetrag.');
        }

        foreach ($manualBuckets as $bucketName => $amount) {
            $bucket = AllocationBucket::tryFrom((string) $bucketName);
            if (! $bucket) {
                throw new \InvalidArgumentException('Unbekannter Verrechnungstopf: '.$bucketName);
            }
            $amount = Money::normalize($amount);
            if (! Money::isPositive($amount)) {
                continue;
            }
            $this->allocateToBucket($payment, $loan, $bucket, $amount, $result);
            $remaining = Money::sub($remaining, $amount);
        }

        return $remaining;
    }

    /**
     * Verteilt einen Betrag innerhalb eines Buckets auf offene Planzeilen
     * (aelteste zuerst); ein Restbetrag wird direkt auf den Bucket gebucht.
     */
    protected function allocateToBucket(Payment $payment, Loan $loan, AllocationBucket $bucket, string $amount, array &$result): void
    {
        $remaining = $amount;
        $itemType = $this->itemTypeForBucket($bucket);

        if ($itemType !== null) {
            foreach ($this->openItems($loan, $itemType, $payment) as $item) {
                if (! Money::isPositive($remaining)) {
                    break;
                }
                $open = $this->openForAllocation($item);
                $take = Money::min($remaining, $open);
                if (! Money::isPositive($take)) {
                    continue;
                }
                $this->applyToItem($payment, $item, $take);
                $this->bookAllocation($payment, $loan, $bucket, $take, $item, $result);
                $remaining = Money::sub($remaining, $take);
            }
        }

        if (Money::isPositive($remaining)) {
            $this->bookAllocation($payment, $loan, $bucket, $remaining, null, $result);
        }
    }

    protected function openAmountForBucket(Payment $payment, Loan $loan, AllocationBucket $bucket): string
    {
        $itemType = $this->itemTypeForBucket($bucket);
        if ($itemType !== null) {
            return Money::sum($this->openItems($loan, $itemType, $payment)->map(fn ($i) => $this->openForAllocation($i)));
        }

        if ($bucket === AllocationBucket::DefaultInterest) {
            return Money::max($this->openDefaultInterest($loan, $payment->payment_date->toDateString()), '0.00');
        }

        return '0.00'; // costs/other: keine automatische Zuordnung
    }

    protected function itemTypeForBucket(AllocationBucket $bucket): ?RepaymentItemType
    {
        return match ($bucket) {
            AllocationBucket::Fees => RepaymentItemType::Fee,
            AllocationBucket::Interest => RepaymentItemType::Interest,
            AllocationBucket::Principal => RepaymentItemType::Principal,
            default => null,
        };
    }

    /** Offene (faellige) Planzeilen chronologisch, aelteste zuerst. */
    protected function openItems(Loan $loan, RepaymentItemType $type, Payment $payment)
    {
        return $loan->repaymentPlanItems()
            ->where('item_type', $type->value)
            ->whereDate('due_date', '<=', $payment->payment_date->toDateString())
            ->whereIn('status', array_map(fn ($s) => $s->value, self::FILLABLE_STATUSES))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Fuer die Verrechnung offener Betrag: SOLL abzueglich ECHTEM IST.
     * Systemseitige Annahmen zaehlen hier nicht als bezahlt; eine echte
     * Zahlung ersetzt die Annahme durch bestaetigte Realitaet.
     */
    protected function openForAllocation(RepaymentPlanItem $item): string
    {
        $real = in_array($item->status, self::REAL_ACTUAL_STATUSES, true)
            ? Money::normalize($item->actual_amount)
            : '0.00';

        return Money::max(Money::sub($item->planned_amount, $real), '0.00');
    }

    protected function applyToItem(Payment $payment, RepaymentPlanItem $item, string $take): void
    {
        $base = in_array($item->status, self::REAL_ACTUAL_STATUSES, true)
            ? Money::normalize($item->actual_amount)
            : '0.00';
        $newActual = Money::add($base, $take);
        $isFull = Money::cmp($newActual, $item->planned_amount) >= 0;
        $isLate = $payment->payment_date->toDateString() > $item->due_date->toDateString();

        $item->update([
            'actual_amount' => $newActual,
            'actual_date' => $payment->payment_date->toDateString(),
            'value_date' => $payment->value_date?->toDateString(),
            'status' => $isFull
                ? ($isLate ? RepaymentItemStatus::Late : RepaymentItemStatus::Confirmed)
                : RepaymentItemStatus::Partial,
            'origin' => $payment->origin ?? PaymentOrigin::ManualEntered,
        ]);
    }

    protected function bookAllocation(Payment $payment, Loan $loan, AllocationBucket $bucket, string $amount, ?RepaymentPlanItem $item, array &$result): void
    {
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'bucket' => $bucket,
            'amount' => $amount,
            'repayment_plan_item_id' => $item?->id,
        ]);

        LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => $this->bookingTypeForBucket($bucket),
            'booking_date' => today()->toDateString(),
            'effective_date' => ($payment->value_date ?? $payment->payment_date)->toDateString(),
            'amount' => Money::negate($amount),
            'description' => $bucket->label().' aus Zahlung vom '.$payment->payment_date->format('d.m.Y'),
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->id,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        $result[$bucket->value] = Money::add($result[$bucket->value] ?? '0.00', $amount);
    }

    protected function bookingTypeForBucket(AllocationBucket $bucket): BookingType
    {
        return match ($bucket) {
            AllocationBucket::Fees => BookingType::FeePayment,
            AllocationBucket::Interest => BookingType::InterestPayment,
            AllocationBucket::Principal => BookingType::Repayment,
            AllocationBucket::DefaultInterest => BookingType::DefaultInterest,
            AllocationBucket::Costs, AllocationBucket::Other => BookingType::Other,
        };
    }

    /** Netto offene Verzugszinsen aus dem Darlehenskonto bis zum Datum. */
    protected function openDefaultInterest(Loan $loan, string $asOfStr): string
    {
        $open = '0.00';
        foreach ($loan->transactions()->with('reversalOf')->get() as $tx) {
            if ($tx->effective_date->toDateString() > $asOfStr) {
                continue;
            }
            $base = $tx->booking_type;
            if (in_array($base, [BookingType::Cancellation, BookingType::Correction], true) && $tx->reversalOf) {
                $base = $tx->reversalOf->booking_type;
            }
            if ($base === BookingType::DefaultInterest) {
                $open = Money::add($open, $tx->amount);
            }
        }

        return $open;
    }

    /**
     * Nach einem Zahlungsstorno: IST der Planzeile aus den verbleibenden,
     * nicht stornierten Zahlungen neu ableiten. Ohne verbleibendes IST
     * faellt die Zeile in den Planzustand zurueck (Grundannahme Abschnitt 24
     * greift bei der naechsten Neuberechnung wieder).
     */
    protected function rebuildItemActuals(RepaymentPlanItem $item, ?string $reason): void
    {
        $remainingAllocations = PaymentAllocation::query()
            ->where('repayment_plan_item_id', $item->id)
            ->whereHas('payment', fn ($q) => $q->where('status', 'recorded'))
            ->with('payment')
            ->get();

        $note = trim(($item->comment ? $item->comment.' | ' : '').'Zahlung storniert'.($reason ? ': '.$reason : ''));

        if ($remainingAllocations->isEmpty()) {
            $item->update([
                'actual_amount' => null,
                'actual_date' => null,
                'value_date' => null,
                'status' => RepaymentItemStatus::Planned,
                'origin' => PaymentOrigin::Assumed,
                'comment' => $note,
            ]);

            return;
        }

        $total = Money::sum($remainingAllocations->pluck('amount'));
        $lastDate = $remainingAllocations
            ->map(fn (PaymentAllocation $a) => $a->payment->payment_date->toDateString())
            ->max();
        $isFull = Money::cmp($total, $item->planned_amount) >= 0;
        $isLate = $lastDate > $item->due_date->toDateString();

        $item->update([
            'actual_amount' => $total,
            'actual_date' => $lastDate,
            'status' => $isFull
                ? ($isLate ? RepaymentItemStatus::Late : RepaymentItemStatus::Confirmed)
                : RepaymentItemStatus::Partial,
            'origin' => PaymentOrigin::Corrected,
            'comment' => $note,
        ]);
    }
}
