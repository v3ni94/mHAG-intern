<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentLink extends Model
{
    protected $guarded = ['id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
