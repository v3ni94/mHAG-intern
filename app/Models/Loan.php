<?php

namespace App\Models;

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
            'default_interest_method' => \App\Enums\InterestMethod::class,
            'interest_method' => \App\Enums\InterestMethod::class,
            'interest_frequency' => \App\Enums\InterestFrequency::class,
            // Fälligkeitstag der Zinsperioden; Standard = bisheriges Verhalten
            'interest_due_day_mode' => \App\Enums\InterestDueDayMode::class,
            'interest_due_day' => 'integer',
            // Zinskapitalisierung: Zuschreibung auf den valutierten Betrag
            'interest_capitalization' => 'boolean',
            'interest_capitalization_from' => 'date',
            'repayment_model' => \App\Enums\RepaymentModel::class,
            'status' => \App\Enums\LoanStatus::class,
            'risk_rating' => \App\Enums\RiskRating::class,
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
     * Datenscope: Externe sehen nur Darlehen, bei denen eine zugeordnete
     * Entity Darlehensgeber oder Darlehensnehmer ist.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }
        $ids = $user->accessibleEntityIds();

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('lender_entity_id', $ids)->orWhereIn('borrower_entity_id', $ids);
        });
    }

    /** Statuswechsel immer hierüber, damit die Historie vollständig bleibt. */
    public function transitionStatus(\App\Enums\LoanStatus $to, ?User $by = null, ?string $note = null, ?\Carbon\Carbon $effectiveDate = null): void
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
