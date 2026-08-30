<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contract extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'contract_template_version_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
