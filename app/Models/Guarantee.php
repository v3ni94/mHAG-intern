<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Guarantee extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'guarantor_entity_id');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
