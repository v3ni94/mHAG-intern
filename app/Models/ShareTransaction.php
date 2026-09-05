<?php

namespace App\Models;

use App\Enums\ShareTransactionStatus;
use App\Enums\ShareTransactionType;
use App\Models\Concerns\GehoertZurHoldingGesellschaft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ShareTransaction extends Model
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

        // Bewegungen betreffen Anteile an der Holding-Gesellschaft.
        if (! self::holdingGesellschaftSichtbar($user)) {
            return $query->whereRaw('1 = 0');
        }

        /*
         * Verborgen, sobald eine beteiligte Seite nicht sichtbar ist, wie beim
         * Darlehen. Andernfalls waeren die Geschaefte der ausgeschlossenen
         * Seite ueber die Gegenseite doch einsehbar.
         *
         * Fehlt eine Seite ganz, etwa bei einer Kapitalerhoehung ohne
         * Verkaeufer, entscheidet allein die vorhandene Seite.
         */
        foreach (['seller', 'buyer'] as $seite) {
            $query->where(function (Builder $q) use ($seite, $user) {
                $q->whereDoesntHave($seite)
                    ->orWhereHas($seite, fn (Builder $sq) => $sq->visibleTo($user));
            });
        }

        return $query;
    }

    protected function casts(): array
    {
        return [
            'type' => ShareTransactionType::class,
            'status' => ShareTransactionStatus::class,
            'price_per_share' => 'decimal:4',
            'total_price' => 'decimal:2',
            'contract_date' => 'date',
            'economic_transfer_date' => 'date',
            'booking_date' => 'date',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class, 'seller_shareholder_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class, 'buyer_shareholder_id');
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(ShareTransaction::class, 'reversal_of');
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
