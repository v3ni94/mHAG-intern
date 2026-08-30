<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractTemplateVersion::class)->orderByDesc('id');
    }

    public function latestVersion(): ?ContractTemplateVersion
    {
        return $this->versions()->first();
    }
}
