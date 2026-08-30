<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInterestTerm extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:6', 'valid_from' => 'date', 'valid_until' => 'date'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
