<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResolutionLink extends Model
{
    protected $guarded = ['id'];

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
