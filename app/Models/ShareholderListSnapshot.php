<?php

namespace App\Models;

use App\Models\Concerns\GehoertZurHoldingGesellschaft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareholderListSnapshot extends Model
{
    use GehoertZurHoldingGesellschaft;

    protected $guarded = ['id'];

    /**
     * Datenscope (Abschnitt 13, Nachtrag vom 05.09.2026).
     *
     * Eine Aktionaersliste ist der vollstaendige Bestand zu einem Stichtag.
     * Sie laesst sich nicht teilweise zeigen: entweder darf jemand den ganzen
     * Bestand sehen oder gar nichts. Deshalb ist sie nur sichtbar, wenn dem
     * Benutzer kein einziger Aktionaer verborgen ist.
     *
     * Das ist die vorsichtige Auslegung. Eine gefilterte Fassung waere keine
     * Aktionaersliste mehr, sondern ein Auszug, und ein Auszug darf nicht wie
     * ein Register aussehen.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }

        if (! self::holdingGesellschaftSichtbar($user)) {
            return $query->whereRaw('1 = 0');
        }

        $verborgen = Shareholder::query()
            ->whereNotIn('id', Shareholder::query()->visibleTo($user)->select('id'))
            ->exists();

        return $verborgen ? $query->whereRaw('1 = 0') : $query;
    }

    public $timestamps = false;

    protected function casts(): array
    {
        return ['as_of_date' => 'date', 'data' => 'array', 'created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
