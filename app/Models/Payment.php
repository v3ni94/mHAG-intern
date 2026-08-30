<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'value_date' => 'date',
            'amount' => 'decimal:2',
            'origin' => \App\Enums\PaymentOrigin::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'payer_entity_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'payee_entity_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
