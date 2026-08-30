<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResolutionVote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['vote' => \App\Enums\VoteChoice::class];
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ResolutionParticipant::class, 'resolution_participant_id');
    }
}
