<?php

namespace App\Models;

use App\Enums\IdentityDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class IdentityDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => IdentityDocumentType::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
