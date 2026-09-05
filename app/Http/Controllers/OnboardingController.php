<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Company;
use App\Models\CorporateBody;
use App\Models\CorporateBodyMember;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Shareholder;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Erste-Schritte-Assistent (Abschnitt 111 Masterprompt).
 *
 * Zehn Schritte mit Erledigungsstand aus den tatsächlich vorhandenen Daten.
 * Der Assistent ist überspringbar und später erneut aufrufbar; der Zustand
 * wird je Benutzer in der vorhandenen settings-Tabelle geführt
 * (Gruppe "onboarding", Schlüssel "user_<id>").
 */
class OnboardingController extends Controller
{
    private const GROUP = 'onboarding';

    public const STATUS_OPEN = 'open';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_COMPLETED = 'completed';

    public function index(Request $request): View
    {
        $user = $request->user();
        $steps = $this->steps($user);
        $doneCount = count(array_filter($steps, fn (array $step) => $step['done']));

        // Sind alle Schritte erledigt, gilt der Assistent als abgeschlossen.
        if ($doneCount === count($steps) && $this->status($user) === self::STATUS_OPEN) {
            $this->setStatus($user, self::STATUS_COMPLETED);
        }

        return view('onboarding.index', [
            'steps' => $steps,
            'doneCount' => $doneCount,
            'totalCount' => count($steps),
            'status' => $this->status($user),
        ]);
    }

    /** Assistent überspringen (Abschnitt 111). */
    public function skip(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->setStatus($user, self::STATUS_SKIPPED);
        AuditService::log('onboarding.skipped', $user);

        return redirect()->route('dashboard')
            ->with('success', 'Der Erste-Schritte-Assistent wurde übersprungen. Sie können ihn jederzeit über Administration, Erste Schritte erneut aufrufen.');
    }

    /** Assistent erneut aufnehmen. */
    public function restart(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->setStatus($user, self::STATUS_OPEN);
        AuditService::log('onboarding.restarted', $user);

        return redirect()->route('onboarding.index')
            ->with('success', 'Der Erste-Schritte-Assistent wurde erneut aufgenommen.');
    }

    // ------------------------------------------------------------------
    // Zustand je Benutzer (settings-Tabelle, keine neue Migration)
    // ------------------------------------------------------------------

    public static function status(User $user): string
    {
        $value = Setting::get(self::GROUP, 'user_'.$user->id);
        $status = is_array($value) ? ($value['status'] ?? null) : $value;

        return in_array($status, [self::STATUS_SKIPPED, self::STATUS_COMPLETED], true)
            ? $status
            : self::STATUS_OPEN;
    }

    private function setStatus(User $user, string $status): void
    {
        Setting::set(self::GROUP, 'user_'.$user->id, [
            'status' => $status,
            'changed_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Soll der Hinweisstreifen im Layout erscheinen? Nur für Benutzer mit
     * Administrationsrechten und nur solange der Assistent offen ist.
     */
    public static function bannerVisible(?User $user): bool
    {
        if ($user === null || ! $user->can('admin.settings')) {
            return false;
        }

        try {
            return self::status($user) === self::STATUS_OPEN;
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Schritte mit Erledigungsstand aus den echten Daten
    // ------------------------------------------------------------------

    /**
     * @return list<array{key:string,title:string,description:string,state:string,done:bool,url:?string,link_label:string}>
     */
    private function steps(User $user): array
    {
        return [
            $this->stepCompanyData(),
            $this->stepBody('board', 'Vorstand anlegen', 'Vorstand'),
            $this->stepBody('supervisory_board', 'Aufsichtsrat anlegen', 'Aufsichtsrat'),
            $this->stepShareholders(),
            $this->stepPersons(),
            $this->stepCompanies(),
            $this->stepLoans(),
            $this->stepSftp(),
            $this->stepTwoFactor($user),
            $this->stepUsers(),
        ];
    }

    private function stepCompanyData(): array
    {
        $entityId = $this->holdingEntityId();
        $company = $entityId ? Company::query()->where('entity_id', $entityId)->first() : null;

        $missing = [];
        if ($company === null) {
            $missing[] = 'Unternehmensdatensatz';
        } else {
            if (blank($company->register_number)) {
                $missing[] = 'Registernummer';
            }
            if (blank($company->register_court)) {
                $missing[] = 'Registergericht';
            }
            if (blank($company->legal_form)) {
                $missing[] = 'Rechtsform';
            }
            $hasAddress = Address::query()->where('entity_id', $entityId)->exists();
            if (! $hasAddress) {
                $missing[] = 'Anschrift';
            }
        }

        return [
            'key' => 'unternehmensdaten',
            'title' => 'Unternehmensdaten prüfen',
            'description' => 'Firma, Rechtsform, Registergericht, Registernummer und Anschrift der eigenen Gesellschaft prüfen.',
            'state' => $company === null
                ? 'Keine eigene Gesellschaft hinterlegt.'
                : ($missing === []
                    ? $company->name.', '.$company->register_court.', '.$company->register_number
                    : 'Angaben unvollständig, es fehlen: '.implode(', ', $missing)),
            'done' => $company !== null && $missing === [],
            'url' => $entityId ? route('companies.show', $entityId) : route('companies.index'),
            'link_label' => 'Unternehmensakte öffnen',
        ];
    }

    private function stepBody(string $type, string $title, string $label): array
    {
        $bodyIds = CorporateBody::query()->where('type', $type)->pluck('id');
        $active = $bodyIds->isEmpty()
            ? 0
            : CorporateBodyMember::query()->whereIn('corporate_body_id', $bodyIds)->where('status', 'active')->count();

        return [
            'key' => $type,
            'title' => $title,
            'description' => 'Mitglieder mit Funktion und Beginn erfassen. Beendete Mandate bleiben mit Enddatum erhalten.',
            'state' => $bodyIds->isEmpty()
                ? 'Gremium noch nicht angelegt.'
                : $label.': '.$active.' '.($active === 1 ? 'aktives Mitglied' : 'aktive Mitglieder'),
            'done' => $active > 0,
            'url' => route('corporate-bodies.index'),
            'link_label' => 'Vorstand und Aufsichtsrat öffnen',
        ];
    }

    private function stepShareholders(): array
    {
        $active = Shareholder::query()->where('status', 'active')->count();
        $effective = ShareTransaction::query()->where('status', 'effective')->count();
        $totalShares = Setting::get('holding', 'total_shares');

        return [
            'key' => 'aktionaere',
            'title' => 'Aktionäre prüfen',
            'description' => 'Aktionäre und den Ausgangsbestand prüfen. Bestände ergeben sich ausschließlich aus wirksamen Aktienbewegungen.',
            'state' => $active === 0
                ? 'Keine aktiven Aktionäre erfasst.'
                : $active.' '.($active === 1 ? 'aktiver Aktionär' : 'aktive Aktionäre')
                    .', '.$effective.' wirksame '.($effective === 1 ? 'Aktienbewegung' : 'Aktienbewegungen')
                    .($totalShares !== null ? ', Grundkapital '.$totalShares.' Aktien' : ''),
            'done' => $active > 0 && $effective > 0,
            'url' => route('shareholders.index'),
            'link_label' => 'Aktionäre öffnen',
        ];
    }

    private function stepPersons(): array
    {
        $count = Person::query()->count();

        return [
            'key' => 'personen',
            'title' => 'Erste Personen anlegen',
            'description' => 'Personen mit Anschrift, Kontaktdaten, Bankverbindung und Ausweisdokumenten erfassen.',
            'state' => $count === 0
                ? 'Noch keine Person erfasst.'
                : $count.' '.($count === 1 ? 'Person erfasst' : 'Personen erfasst'),
            'done' => $count > 0,
            'url' => route('persons.index'),
            'link_label' => 'Personen öffnen',
        ];
    }

    private function stepCompanies(): array
    {
        $count = Company::query()->count();
        $others = max(0, $count - ($this->holdingEntityId() !== null ? 1 : 0));

        return [
            'key' => 'unternehmen',
            'title' => 'Erste Unternehmen anlegen',
            'description' => 'Weitere Unternehmen mit Registerdaten, Organen und Beteiligungsverhältnissen erfassen.',
            'state' => $count.' '.($count === 1 ? 'Unternehmen erfasst' : 'Unternehmen erfasst')
                .' (davon '.$others.' neben der eigenen Gesellschaft)',
            'done' => $others > 0,
            'url' => route('companies.index'),
            'link_label' => 'Unternehmen öffnen',
        ];
    }

    private function stepLoans(): array
    {
        $count = Loan::query()->count();

        return [
            'key' => 'darlehen',
            'title' => 'Erstes Darlehen anlegen',
            'description' => 'Vertragsdaten, Wirkungsbeginn, Zinskonditionen und Rückzahlungsmodell erfassen. Der Zahlungsplan wird daraus erzeugt.',
            'state' => $count === 0
                ? 'Noch kein Darlehen erfasst.'
                : $count.' '.($count === 1 ? 'Darlehen erfasst' : 'Darlehen erfasst'),
            'done' => $count > 0,
            'url' => route('loans.index'),
            'link_label' => 'Darlehen öffnen',
        ];
    }

    private function stepSftp(): array
    {
        $test = Setting::get('sftp', 'last_test');
        $online = is_array($test) ? (bool) ($test['online'] ?? false) : false;
        $testedAt = is_array($test) ? ($test['tested_at'] ?? null) : null;

        return [
            'key' => 'sftp',
            'title' => 'SFTP testen',
            'description' => 'Verbindung zum Dokumentenspeicher prüfen (Lesen, Schreiben, Umbenennen).',
            'state' => $test === null
                ? 'Noch kein Verbindungstest durchgeführt.'
                : ($online ? 'Letzter Test erfolgreich' : 'Letzter Test fehlgeschlagen')
                    .($testedAt ? ' am '.format_datetime($testedAt) : ''),
            'done' => $online,
            'url' => route('admin.sftp.index'),
            'link_label' => 'SFTP-Status öffnen',
        ];
    }

    private function stepTwoFactor(User $user): array
    {
        $confirmed = $user->two_factor_confirmed_at !== null;

        return [
            'key' => 'zwei-faktor',
            'title' => 'Zwei-Faktor-Authentifizierung prüfen',
            'description' => 'Für Administration, Vorstand und Aufsichtsrat verpflichtend. Wiederherstellungscodes sicher aufbewahren.',
            'state' => $confirmed
                ? 'Für Ihr Konto eingerichtet und bestätigt'
                : 'Für Ihr Konto noch nicht bestätigt.',
            'done' => $confirmed,
            'url' => route('two-factor.setup'),
            'link_label' => 'Zwei-Faktor-Authentifizierung öffnen',
        ];
    }

    private function stepUsers(): array
    {
        $users = User::query()->count();
        $openInvitations = UserInvitation::query()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->count();

        return [
            'key' => 'benutzer',
            'title' => 'Benutzer einladen',
            'description' => 'Benutzer mit Rolle und Datenbereich einladen. Der Zugang wird über einen Einladungslink vergeben.',
            'state' => $users.' '.($users === 1 ? 'Benutzerkonto' : 'Benutzerkonten')
                .', '.$openInvitations.' offene '.($openInvitations === 1 ? 'Einladung' : 'Einladungen'),
            'done' => $users > 1 || $openInvitations > 0,
            'url' => route('admin.invitations.index'),
            'link_label' => 'Einladungen öffnen',
        ];
    }

    private function holdingEntityId(): ?int
    {
        $id = Setting::get('holding', 'company_entity_id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        return Entity::query()->where('internal_number', 'ENT-MHAG')->value('id');
    }
}
