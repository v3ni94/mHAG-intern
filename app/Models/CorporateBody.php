<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateBody extends Model
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

        return $query->whereHas('company', fn (Builder $q) => $q->visibleTo($user));
    }

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
