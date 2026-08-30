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
            'entity_scope_mode' => \App\Enums\EntityScopeMode::class,
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

    /** Ist ein Profilbild hinterlegt und noch vorhanden? */
    public function hasAvatar(): bool
    {
        if (! $this->avatar_path) {
            return false;
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk('avatars')->exists($this->avatar_path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Initialen als schriftlicher Rueckfall, wenn kein Bild hinterlegt ist.
     * Titel und Zusaetze werden dabei nicht beruecksichtigt.
     */
    public function initials(): string
    {
        $teile = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $teile = array_values(array_filter($teile, fn ($t) => $t !== '' && mb_strlen($t) > 1));

        if ($teile === []) {
            return mb_strtoupper(mb_substr((string) $this->email, 0, 1));
        }

        $erste = mb_substr($teile[0], 0, 1);
        $letzte = count($teile) > 1 ? mb_substr($teile[count($teile) - 1], 0, 1) : '';

        return mb_strtoupper($erste.$letzte);
    }

    /**
     * Sichtbarkeitsmodus. Standard ist der Einschluss, also das bisherige
     * Verhalten: sichtbar sind nur die zugeordneten Gesellschaften.
     */
    public function entityScopeMode(): \App\Enums\EntityScopeMode
    {
        if ($this->entity_scope_mode instanceof \App\Enums\EntityScopeMode) {
            return $this->entity_scope_mode;
        }

        return \App\Enums\EntityScopeMode::tryFrom((string) $this->entity_scope_mode)
            ?? \App\Enums\EntityScopeMode::Include;
    }

    /** Arbeitet dieser Benutzer im Ausschlussmodus (alles ausser ...)? */
    public function usesEntityExclusion(): bool
    {
        return ! $this->isInternal()
            && $this->entityScopeMode() === \App\Enums\EntityScopeMode::Exclude;
    }

    /**
     * Ausdruecklich zugeordnete Entities. Je nach Modus ist das die
     * Erlaubnisliste oder die Ausschlussliste.
     */
    public function scopedEntityIds(): Collection
    {
        $ids = $this->entityAssignments->pluck('entity_id');
        if ($this->entity_id) {
            $ids->push($this->entity_id);
        }

        return $ids->filter()->unique()->values();
    }

    /**
     * Ausgeschlossene Entities. Nur im Ausschlussmodus belegt; im
     * Einschlussmodus leer, damit bestehende Auswertungen unveraendert
     * bleiben.
     */
    public function excludedEntityIds(): Collection
    {
        if (! $this->usesEntityExclusion()) {
            return collect();
        }

        // Die eigene Entity wird nicht ausgeschlossen: der Benutzer soll die
        // eigene Akte sehen. Ausgeschlossen wird nur, was ausdruecklich
        // zugeordnet ist.
        return $this->entityAssignments->pluck('entity_id')->filter()->unique()->values();
    }

    /**
     * IDs aller Entities, auf die dieser Benutzer Zugriff hat.
     *
     * Einschlussmodus: eigene Entity und Zuordnungen (bisheriges Verhalten).
     * Ausschlussmodus: alle Entities ausser den zugeordneten. Neu angelegte
     * Gesellschaften sind damit automatisch sichtbar, was der fachlichen
     * Vorgabe "alles ausser X" entspricht.
     */
    public function accessibleEntityIds(): Collection
    {
        if ($this->usesEntityExclusion()) {
            $ausgeschlossen = $this->excludedEntityIds()->all();

            return Entity::query()
                ->when($ausgeschlossen !== [], fn ($q) => $q->whereNotIn('id', $ausgeschlossen))
                ->pluck('id');
        }

        return $this->scopedEntityIds();
    }

    /** Sitzungsschluessel des gewaehlten Ansichtskontexts. */
    public const CONTEXT_SESSION_KEY = 'context_assignment_id';

    /** Wert fuer die ungefilterte Gesamtansicht. */
    public const CONTEXT_ALL = 'all';

    /**
     * Zuordnungen, die als Ansicht zur Auswahl stehen (Abschnitt 13).
     * Im Ausschlussmodus sind die Zuordnungen Ausschluesse und taugen nicht
     * als Ansicht.
     *
     * @return Collection<int, UserEntityAssignment>
     */
    public function availableContexts(): Collection
    {
        if ($this->usesEntityExclusion()) {
            return collect();
        }

        // Die Bezeichnung der Ansicht braucht den Namen der Gesellschaft.
        // Ohne dieses Nachladen entstuende je Zuordnung eine eigene Abfrage.
        $this->loadMissing('entityAssignments.entity');

        return $this->entityAssignments
            ->filter(fn (UserEntityAssignment $a) => $a->entity_id !== null)
            ->sortBy(fn (UserEntityAssignment $a) => $a->viewLabel())
            ->values();
    }

    /**
     * Aktiver Ansichtskontext (Kontextwechsel, Session-basiert).
     *
     * Ohne ausdrueckliche Wahl gilt die Zuordnung mit Standardkennzeichen,
     * sonst die Gesamtansicht (null). Die Gesamtansicht ist bewusst der
     * Rueckfall: eine stillschweigende Einschraenkung wuerde wie Datenverlust
     * wirken.
     */
    public function currentContext(): ?UserEntityAssignment
    {
        $gewaehlt = session(self::CONTEXT_SESSION_KEY);

        if ($gewaehlt === self::CONTEXT_ALL) {
            return null;
        }

        $verfuegbar = $this->availableContexts();

        if ($gewaehlt !== null) {
            $treffer = $verfuegbar->firstWhere('id', (int) $gewaehlt);
            if ($treffer) {
                return $treffer;
            }
        }

        return $verfuegbar->firstWhere('is_default', true);
    }

    /**
     * Gesellschaft, auf die die aktuelle Ansicht eingeschraenkt ist.
     * null bedeutet Gesamtansicht, also keine Einschraenkung.
     */
    public function viewEntityId(): ?int
    {
        return $this->currentContext()?->entity_id;
    }

    /**
     * Organmandate dieser Person aus den Organen der Gesellschaften
     * (Vorstand, Geschaeftsfuehrung, Aufsichtsrat). Grundlage fuer den
     * Vorschlag, welche Gesellschaften als Ansicht freigegeben werden
     * koennen. Ein Mandat allein gewaehrt KEINEN Datenzugriff; die Freigabe
     * erfolgt ausdruecklich durch die Administration.
     *
     * @return Collection<int, CorporateBodyMember>
     */
    public function organMandates(): Collection
    {
        if (! $this->entity_id) {
            return collect();
        }

        return CorporateBodyMember::query()
            ->with('body.company:id,display_name')
            ->where('person_entity_id', $this->entity_id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (CorporateBodyMember $m) => $m->body?->company_entity_id !== null)
            ->values();
    }
}
