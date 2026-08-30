<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactDetail extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
