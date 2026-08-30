<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Datenscope (Abschnitt 13): Ein Vertrag ist sichtbar, wenn das
     * zugehörige Darlehen sichtbar ist. Interne Rollen sehen den
     * Gesamtbestand.
     *
     * Die Prüfung lag zuvor nur als private Methode im ContractController.
     * Der Nachtrag zu einem Vertrag wurde deshalb ohne jede
     * Sichtbarkeitsprüfung geschrieben. Als Scope am Modell steht sie allen
     * Aufrufern zur Verfügung und kann nicht mehr vergessen werden.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }

        return $query->whereHas('loan', fn (Builder $lq) => $lq->visibleTo($user));
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
