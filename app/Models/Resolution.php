<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Resolution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\ResolutionType::class,
            'status' => \App\Enums\ResolutionStatus::class,
            'resolved_on' => 'date',
            'recorded_at' => 'datetime',
            'conflict_of_interest' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'company_entity_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'applicant_entity_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ResolutionParticipant::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ResolutionVote::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ResolutionLink::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }

    public function signatureRequests(): MorphMany
    {
        return $this->morphMany(SignatureRequest::class, 'subject');
    }
}
