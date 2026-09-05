<?php

namespace App\Models;

use App\Enums\ResolutionStatus;
use App\Enums\ResolutionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Resolution extends Model
{
    protected $guarded = ['id'];

    /**
     * Datenscope (Abschnitt 13, Nachtrag vom 05.09.2026).
     *
     * Bis dahin hatte kein Modell des Holding-Bereichs eine Einschraenkung
     * nach Gesellschaft. Fuer die Rolle Partner ohne Folgen, weil dort schon
     * die Berechtigung sperrt. Die externen Aufsichtsratsrollen besitzen aber
     * shares.view und resolutions.view; ein je Benutzer gesetzter Ausschluss
     * wirkte dort nicht.
     *
     * Die Einschraenkung greift nur bei Benutzern, fuer die tatsaechlich eine
     * gesetzt ist. Interne Rollen sehen unveraendert den Gesamtbestand.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }

        /*
         * Anker ist die beschliessende Gesellschaft. Der Antragsteller bleibt
         * bewusst aussen vor: Ein Beschluss der Mueller Holding AG soll nicht
         * deshalb verschwinden, weil die antragstellende Person
         * ausgeschlossen ist. Sichtbarkeit haengt an der Gesellschaft.
         */
        return $query->whereHas('company', fn (Builder $q) => $q->visibleTo($user));
    }

    protected function casts(): array
    {
        return [
            'type' => ResolutionType::class,
            'status' => ResolutionStatus::class,
            'resolved_on' => 'date',
            'recorded_at' => 'datetime',
            'conflict_of_interest' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'company_entity_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'applicant_entity_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ResolutionParticipant::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ResolutionVote::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ResolutionLink::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }

    public function signatureRequests(): MorphMany
    {
        return $this->morphMany(SignatureRequest::class, 'subject');
    }
}
