<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateBody extends Model
{
    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'company_entity_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CorporateBodyMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }
}
