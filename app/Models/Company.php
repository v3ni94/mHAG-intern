<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $guarded = ['id'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    protected function casts(): array
    {
        return ['founded_on' => 'date'];
    }
}
