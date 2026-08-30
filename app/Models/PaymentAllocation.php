<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bucket' => \App\Enums\AllocationBucket::class,
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function repaymentPlanItem(): BelongsTo
    {
        return $this->belongsTo(RepaymentPlanItem::class);
    }
}
