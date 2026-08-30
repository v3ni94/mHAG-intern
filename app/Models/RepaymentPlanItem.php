<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepaymentPlanItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'item_type' => \App\Enums\RepaymentItemType::class,
            'due_date' => 'date',
            'planned_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'status' => \App\Enums\RepaymentItemStatus::class,
            'origin' => \App\Enums\PaymentOrigin::class,
            'actual_date' => 'date',
            'value_date' => 'date',
            'manually_adjusted' => 'boolean',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * IST-Betrag unter Berücksichtigung der systemseitigen Annahme:
     * Solange keine Abweichung erfasst ist, gilt planmäßige Erfüllung
     * als angenommen (Abschnitt 24), deutlich gekennzeichnet über origin.
     */
    public function effectiveActual(): string
    {
        return match ($this->status) {
            \App\Enums\RepaymentItemStatus::Assumed => \App\Support\Money::normalize($this->planned_amount),
            \App\Enums\RepaymentItemStatus::Confirmed,
            \App\Enums\RepaymentItemStatus::Partial,
            \App\Enums\RepaymentItemStatus::Late => \App\Support\Money::normalize($this->actual_amount),
            default => '0.00',
        };
    }

    public function openAmount(): string
    {
        $open = \App\Support\Money::sub($this->planned_amount, $this->effectiveActual());

        return \App\Support\Money::isNegative($open) ? '0.00' : $open;
    }
}
