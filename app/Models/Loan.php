<?php

namespace App\Models;

use App\Enums\InterestDueDayMode;
use App\Enums\InterestFrequency;
use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\RepaymentModel;
use App\Enums\RiskRating;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_date' => 'date',
            'effective_from' => 'date',
            'disbursement_date' => 'date',
            'due_date' => 'date',
            'contract_end' => 'date',
            'principal_amount' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'default_interest_rate' => 'decimal:6',
            'default_interest_enabled' => 'boolean',
            // Verzugszinsen (Abschnitt 44): fachliche Vorgaben, keine Vorbelegung
            'default_interest_start' => 'date',
            'default_interest_method' => InterestMethod::class,
            'interest_method' => InterestMethod::class,
            'interest_frequency' => InterestFrequency::class,
            // Fälligkeitstag der Zinsperioden; Standard = bisheriges Verhalten
            'interest_due_day_mode' => InterestDueDayMode::class,
            'interest_due_day' => 'integer',
            'interest_due_month' => 'integer',
            // Zinskapitalisierung: Zuschreibung auf den valutierten Betrag
            'interest_capitalization' => 'boolean',
            'interest_capitalization_from' => 'date',
            // Ausfall (nicht Verzug): Wirkungsdatum der Erfassung
            'defaulted_on' => 'date',
            'repayment_model' => RepaymentModel::class,
            'status' => LoanStatus::class,
            'risk_rating' => RiskRating::class,
            'tags' => 'array',
        ];
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'lender_entity_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'borrower_entity_id');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class)->orderBy('created_at');
    }

    public function interestTerms(): HasMany
    {
        return $this->hasMany(LoanInterestTerm::class)->orderBy('valid_from');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(LoanFee::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(LoanDisbursement::class)->orderBy('planned_date');
    }

    public function repaymentPlanItems(): HasMany
    {
        return $this->hasMany(RepaymentPlanItem::class)->orderBy('due_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class)->orderBy('effective_date')->orderBy('id');
    }

    public function recalculations(): HasMany
    {
        return $this->hasMany(LoanRecalculation::class)->latest('created_at');
    }

    public function securities(): HasMany
    {
        return $this->hasMany(Security::class);
    }

    public function guarantees(): HasMany
    {
        return $this->hasMany(Guarantee::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Datenscope (Abschnitt 14).
     *
     * Einschlussmodus: sichtbar sind Darlehen, bei denen eine zugeordnete
     * Entity Darlehensgeber oder Darlehensnehmer ist.
     *
     * Ausschlussmodus (Partner): sichtbar ist alles, AUSSER Darlehen, an denen
     * eine ausgeschlossene Gesellschaft beteiligt ist. Bewusst streng: sobald
     * eine ausgeschlossene Gesellschaft auf einer der beiden Seiten steht,
     * bleibt das Darlehen verborgen. Andernfalls waeren die Geschaefte der
     * ausgeschlossenen Gesellschaft ueber die Gegenseite doch einsehbar.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }

        if ($user->usesEntityExclusion()) {
            $ausgeschlossen = $user->excludedEntityIds()->all();
            if ($ausgeschlossen === []) {
                return $query;
            }

            return $query->whereNotIn('lender_entity_id', $ausgeschlossen)
                ->whereNotIn('borrower_entity_id', $ausgeschlossen);
        }

        $ids = $user->accessibleEntityIds();

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('lender_entity_id', $ids)->orWhereIn('borrower_entity_id', $ids);
        });
    }

    /**
     * Einschraenkung auf die gewaehlte Ansicht (Abschnitt 13).
     *
     * Wirkt ausschliesslich in Listen, Auswertungen und Reports, nicht beim
     * direkten Aufruf eines Vorgangs: die Ansicht ist ein Filter, keine
     * Berechtigung. Ohne gewaehlte Ansicht (Gesamtansicht) bleibt die Abfrage
     * unveraendert.
     */
    public function scopeInCurrentView(Builder $query, User $user): Builder
    {
        $entityId = $user->viewEntityId();
        if ($entityId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($entityId) {
            $q->where('lender_entity_id', $entityId)->orWhere('borrower_entity_id', $entityId);
        });
    }

    /** Statuswechsel immer hierüber, damit die Historie vollständig bleibt. */
    public function transitionStatus(LoanStatus $to, ?User $by = null, ?string $note = null, ?Carbon $effectiveDate = null): void
    {
        $from = $this->status;
        if ($from === $to) {
            return;
        }
        $this->update(['status' => $to]);
        $this->statusHistory()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $by?->id,
            'note' => $note,
            'effective_date' => $effectiveDate?->toDateString(),
        ]);
    }
}
