<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRole extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role' => \App\Enums\OrganizationRoleType::class,
            'started_on' => 'date',
            'ended_on' => 'date',
            'is_active' => 'boolean',
            'sole_representation' => 'boolean',
            'exemption_181' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'company_entity_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'person_entity_id');
    }
}
