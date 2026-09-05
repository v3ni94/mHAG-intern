<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResolutionParticipant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'excluded_from_deliberation' => 'boolean',
            'excluded_from_vote' => 'boolean',
        ];
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function vote(): HasOne
    {
        return $this->hasOne(ResolutionVote::class, 'resolution_participant_id');
    }
}
