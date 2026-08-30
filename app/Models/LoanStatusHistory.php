<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanStatusHistory extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $table = 'loan_status_history';

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'created_at' => 'datetime'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
