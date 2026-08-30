<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CorporateBodyMember extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_chair' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function body(): BelongsTo
    {
        return $this->belongsTo(CorporateBody::class, 'corporate_body_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'person_entity_id');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
