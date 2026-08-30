<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shareholder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'left_on' => 'date'];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ShareTransaction::class, 'buyer_shareholder_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ShareTransaction::class, 'seller_shareholder_id');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
