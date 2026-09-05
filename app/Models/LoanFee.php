<?php

namespace App\Models;

use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanFee extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => FeeType::class,
            'amount' => 'decimal:2',
            'percentage' => 'decimal:6',
            'due_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
