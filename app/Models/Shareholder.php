<?php

namespace App\Models;

use App\Models\Concerns\GehoertZurHoldingGesellschaft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shareholder extends Model
{
    use GehoertZurHoldingGesellschaft;

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

        // Das Aktienregister ist eine Angelegenheit der Holding-Gesellschaft.
        // Ist sie ausgeschlossen, ist auch das Register verborgen.
        if (! self::holdingGesellschaftSichtbar($user)) {
            return $query->whereRaw('1 = 0');
        }

        // Darueber hinaus ist ein Aktionaer nur sichtbar, wenn die
        // dahinterliegende Person oder Gesellschaft sichtbar ist.
        return $query->whereHas('entity', fn (Builder $q) => $q->visibleTo($user));
    }

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'left_on' => 'date'];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ShareTransaction::class, 'buyer_shareholder_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ShareTransaction::class, 'seller_shareholder_id');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
