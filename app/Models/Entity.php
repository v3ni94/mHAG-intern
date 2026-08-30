<?php

namespace App\Models;

use App\Enums\EntityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zentraler Geschäftspartnerstamm: Eine Entity ist Privatperson, Unternehmen
 * oder sonstige Organisation und kann gleichzeitig mehrere Rollen besitzen
 * (Darlehensgeber, Darlehensnehmer, Aktionär, Organmitglied, ...).
 */
class Entity extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => EntityType::class,
            'tags' => 'array',
        ];
    }

    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function contactDetails(): HasMany
    {
        return $this->hasMany(ContactDetail::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function taxDetail(): HasOne
    {
        return $this->hasOne(TaxDetail::class);
    }

    public function identityDocuments(): HasMany
    {
        return $this->hasMany(IdentityDocument::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Organstellungen dieser Person in Unternehmen. */
    public function organizationRolesAsPerson(): HasMany
    {
        return $this->hasMany(OrganizationRole::class, 'person_entity_id');
    }

    /** Organe/Funktionsträger dieses Unternehmens. */
    public function organizationRolesAsCompany(): HasMany
    {
        return $this->hasMany(OrganizationRole::class, 'company_entity_id');
    }

    public function relationshipsAsA(): HasMany
    {
        return $this->hasMany(EntityRelationship::class, 'entity_a_id');
    }

    public function relationshipsAsB(): HasMany
    {
        return $this->hasMany(EntityRelationship::class, 'entity_b_id');
    }

    public function loansAsLender(): HasMany
    {
        return $this->hasMany(Loan::class, 'lender_entity_id');
    }

    public function loansAsBorrower(): HasMany
    {
        return $this->hasMany(Loan::class, 'borrower_entity_id');
    }

    public function shareholder(): HasOne
    {
        return $this->hasOne(Shareholder::class);
    }

    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function primaryAddress(): ?Address
    {
        return $this->addresses->firstWhere('is_primary', true) ?? $this->addresses->first();
    }

    public function primaryEmail(): ?string
    {
        return $this->contactDetails
            ->where('type', 'email')
            ->sortByDesc('is_primary')
            ->first()?->value;
    }

    /** Anzeigenamen aus Person/Unternehmen pflegen. */
    public function refreshDisplayName(): void
    {
        if ($this->type === EntityType::Person && $this->person) {
            $this->display_name = $this->person->fullName();
        } elseif ($this->type === EntityType::Company && $this->company) {
            $this->company->refresh();
            $this->display_name = $this->company->name;
        }
        $this->save();
    }

    /** Datenscope: Externe Benutzer sehen nur explizit zugeordnete Entities. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isInternal()) {
            return $query;
        }

        return $query->whereIn('id', $user->accessibleEntityIds());
    }
}
