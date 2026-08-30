<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'is_primary' => 'boolean'];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function oneLine(): string
    {
        $street = trim(($this->street ?? '').' '.($this->house_number ?? ''));

        return trim(implode(', ', array_filter([$street, trim(($this->postal_code ?? '').' '.($this->city ?? ''))])));
    }
}
