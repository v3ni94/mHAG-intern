<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Investment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'share_percentage' => 'decimal:6',
            'acquired_on' => 'date',
            'acquisition_cost' => 'decimal:2',
            'current_value' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'company_entity_id');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
