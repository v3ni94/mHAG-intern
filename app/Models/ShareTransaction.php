<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ShareTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\ShareTransactionType::class,
            'status' => \App\Enums\ShareTransactionStatus::class,
            'price_per_share' => 'decimal:4',
            'total_price' => 'decimal:2',
            'contract_date' => 'date',
            'economic_transfer_date' => 'date',
            'booking_date' => 'date',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class, 'seller_shareholder_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class, 'buyer_shareholder_id');
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(ShareTransaction::class, 'reversal_of');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
