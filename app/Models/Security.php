<?php

namespace App\Models;

use App\Enums\SecurityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Security extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => SecurityType::class,
            'nominal_value' => 'decimal:2',
            'internal_value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'provider_entity_id');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
