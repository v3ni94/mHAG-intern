<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityRelationship extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'relationship_type' => \App\Enums\RelationshipType::class,
            'share_percentage' => 'decimal:6',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function entityA(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_a_id');
    }

    public function entityB(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_b_id');
    }
}
