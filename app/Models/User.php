<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** Rollen, die vollen internen Datenzugriff haben (kein Entity-Scoping). */
    public const INTERNAL_ROLES = [
        'Administrator', 'Vorstand', 'Buchhaltung', 'Sachbearbeiter', 'Mitarbeiter', 'Nur Lesen',
    ];

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'privacy_mode' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function entityAssignments(): HasMany
    {
        return $this->hasMany(UserEntityAssignment::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * 2FA-Pflicht laut Rolle (Administrator, Vorstand, Aufsichtsrat) bzw. Einstellung.
     */
    public function requiresTwoFactor(): bool
    {
        $required = Setting::get('security', 'two_factor_required_roles', [
            'Administrator', 'Vorstand', 'Aufsichtsratsvorsitzender', 'Aufsichtsratsmitglied',
        ]);

        return $this->roles->pluck('name')->intersect($required)->isNotEmpty();
    }

    /**
     * Interne Rollen sehen den Gesamtbestand; externe nur zugeordnete Entities.
     */
    public function isInternal(): bool
    {
        return $this->roles->pluck('name')->intersect(self::INTERNAL_ROLES)->isNotEmpty();
    }

    /**
     * IDs aller Entities, auf die dieser Benutzer Zugriff hat (eigene Entity + Zuordnungen).
     */
    public function accessibleEntityIds(): Collection
    {
        $ids = $this->entityAssignments->pluck('entity_id');
        if ($this->entity_id) {
            $ids->push($this->entity_id);
        }

        return $ids->unique()->values();
    }

    /**
     * Aktiver Ansichtskontext (Kontextwechsel, Session-basiert).
     */
    public function currentContext(): ?UserEntityAssignment
    {
        $id = session('context_assignment_id');
        if ($id) {
            $assignment = $this->entityAssignments->firstWhere('id', $id);
            if ($assignment) {
                return $assignment;
            }
        }

        return $this->entityAssignments->firstWhere('is_default', true)
            ?? $this->entityAssignments->first();
    }
}
