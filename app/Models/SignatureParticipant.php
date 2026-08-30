<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureParticipant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\SignatureParticipantStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'signature_request_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
