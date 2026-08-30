<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\UserStoreRequest;
use App\Http\Requests\Organisation\UserUpdateRequest;
use App\Mail\UserAccountChangedMail;
use App\Mail\UserCredentialsMail;
use App\Models\Entity;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MailDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Benutzerverwaltung (Abschnitt 9 Masterprompt): Rollen, 2FA-Status,
 * letzter Login, Datenbereich (user_entity_assignments), aktiv/inaktiv.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $showArchived = $request->boolean('archiviert');

        $users = User::query()
            ->when($showArchived, fn ($q) => $q->onlyTrashed())
            ->with('roles:id,name')
            ->withCount('entityAssignments')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->query('search').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'showArchived' => $showArchived,
            'archivedCount' => User::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
            'entities' => Entity::query()->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'entity_id' => $request->input('entity_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $user->syncRoles($request->input('roles', []));

        AuditService::log('admin.users.created', $user, [], [
            'name' => $user->name, 'email' => $user->email, 'roles' => $request->input('roles', []),
        ]);

        $message = 'Der Benutzer wurde angelegt.';

        // Optional: Zugangsdaten direkt per E-Mail zustellen
        if ($request->boolean('send_credentials')) {
            $result = $this->dispatchCredentials($user, 'Ihr Konto wurde neu eingerichtet.');
            $message .= $result['sent']
                ? ' Die Zugangsdaten wurden an '.$user->email.' versendet.'
                : ' Der Versand der Zugangsdaten ist fehlgeschlagen: '.$result['error'];
        }

        return redirect()->route('admin.users.show', $user)->with(
            $request->boolean('send_credentials') && ! ($result['sent'] ?? true) ? 'warning' : 'success',
            $message,
        );
    }

    public function show(User $user): View
    {
        $user->load(['roles:id,name', 'entityAssignments.entity:id,display_name', 'entity:id,display_name']);

        return view('admin.users.show', [
            'user' => $user,
            'lastLogins' => \App\Models\LoginAttempt::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load(['roles:id,name', 'entityAssignments.entity:id,display_name']);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'entities' => Entity::query()->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'entity_id' => $user->entity_id,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->all(),
        ];

        $user->fill([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'entity_id' => $request->input('entity_id'),
            'is_active' => $request->boolean('is_active'),
        ]);
        if ($request->filled('password')) {
            $user->password = $request->input('password');
        }
        $user->save();
        $user->syncRoles($request->input('roles', []));

        // Datenbereich vollständig synchronisieren
        $assignments = collect($request->input('assignments', []))
            ->filter(fn ($a) => ! empty($a['entity_id']));
        $keepIds = [];
        foreach ($assignments as $assignment) {
            $row = $user->entityAssignments()->updateOrCreate(
                ['entity_id' => (int) $assignment['entity_id'], 'context' => $assignment['context'] ?? 'self'],
                [
                    'label' => $assignment['label'] ?? null,
                    'is_default' => (bool) ($assignment['is_default'] ?? false),
                ],
            );
            $keepIds[] = $row->id;
        }
        $user->entityAssignments()->whereNotIn('id', $keepIds)->delete();

        AuditService::log('admin.users.updated', $user, $old, [
            'name' => $user->name,
            'email' => $user->email,
            'entity_id' => $user->entity_id,
            'is_active' => $user->is_active,
            'roles' => $request->input('roles', []),
            'assignments' => $assignments->values()->all(),
        ]);

        $message = 'Der Benutzer wurde aktualisiert.';

        // Betroffenen auf Wunsch über die Änderung informieren
        if ($request->boolean('notify_user')) {
            $newRoles = $user->fresh()->roles->pluck('name')->all();
            $changes = [];
            if ($old['roles'] !== $newRoles) {
                $changes[] = 'Zugeordnete Rollen: '.(empty($newRoles) ? 'keine' : implode(', ', $newRoles));
            }
            if ($old['is_active'] !== $user->is_active) {
                $changes[] = $user->is_active ? 'Ihr Konto wurde aktiviert.' : 'Ihr Konto wurde deaktiviert.';
            }
            if ($old['email'] !== $user->email) {
                $changes[] = 'Ihre Anmeldeadresse lautet nun: '.$user->email;
            }

            if ($changes !== []) {
                $result = MailDispatcher::send(
                    $user->email,
                    new UserAccountChangedMail($user, $changes),
                    'admin.users.change_notified',
                    $user,
                );
                $message .= $result['sent']
                    ? ' Der Benutzer wurde per E-Mail informiert.'
                    : ' Die Benachrichtigung konnte nicht versendet werden: '.$result['error'];
            } else {
                $message .= ' Es gab keine mitteilungspflichtige Änderung, daher wurde keine E-Mail versendet.';
            }
        }

        return redirect()->route('admin.users.show', $user)->with('success', $message);
    }

    /**
     * Zugangsdaten per E-Mail zustellen: Anmeldeadresse, Rollen und ein
     * zeitlich begrenzter Link zum Setzen eines eigenen Passworts.
     */
    public function sendCredentials(Request $request, User $user): RedirectResponse
    {
        $result = $this->dispatchCredentials($user, $request->input('note'));

        return back()->with(
            $result['sent'] ? 'success' : 'warning',
            $result['sent']
                ? 'Die Zugangsdaten wurden an '.$user->email.' versendet.'
                : 'Der Versand ist fehlgeschlagen: '.$result['error'],
        );
    }

    /**
     * Link zum Zurücksetzen des Passworts an den Benutzer senden.
     */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);
        $sent = $status === Password::RESET_LINK_SENT;

        AuditService::log(
            $sent ? 'admin.users.password_reset_sent' : 'admin.users.password_reset_failed',
            $user,
            [],
            [],
            ['status' => $status],
        );

        return back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Ein Link zum Zurücksetzen des Passworts wurde an '.$user->email.' versendet.'
                : 'Der Versand ist fehlgeschlagen. Bitte prüfen Sie die Mailkonfiguration unter Einstellungen.',
        );
    }

    /**
     * Zwei-Faktor-Authentifizierung zurücksetzen, etwa bei Verlust des Geräts.
     * Bei Pflichtrollen wird die Einrichtung bei der nächsten Anmeldung erneut
     * erzwungen.
     */
    public function resetTwoFactor(User $user): RedirectResponse
    {
        $wasEnabled = $user->hasTwoFactorEnabled();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        AuditService::log('admin.users.two_factor_reset', $user, ['two_factor_enabled' => $wasEnabled], ['two_factor_enabled' => false]);

        $result = MailDispatcher::send(
            $user->email,
            new UserAccountChangedMail(
                $user,
                [
                    'Die Zwei-Faktor-Authentifizierung Ihres Kontos wurde zurückgesetzt.',
                    $user->requiresTwoFactor()
                        ? 'Bei der nächsten Anmeldung werden Sie zur erneuten Einrichtung geführt, da für Ihre Rolle ein zweiter Faktor verpflichtend ist.'
                        : 'Sie können die Zwei-Faktor-Authentifizierung im Profil erneut einrichten.',
                ],
                'Zwei-Faktor-Authentifizierung zurückgesetzt',
            ),
            'admin.users.two_factor_reset_notified',
            $user,
        );

        return back()->with('success', 'Die Zwei-Faktor-Authentifizierung wurde zurückgesetzt.'
            .($result['sent'] ? ' Der Benutzer wurde per E-Mail informiert.' : ' Hinweis: Die Benachrichtigung konnte nicht versendet werden.'));
    }

    /**
     * Konto archivieren. Bewusst kein endgültiges Löschen: Benutzer sind mit
     * Audit-Einträgen und Vorgängen verknüpft, die erhalten bleiben müssen.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Das eigene Konto kann nicht archiviert werden.');

        $user->update(['is_active' => false]);
        $user->delete();

        AuditService::log('admin.users.archived', $user, ['is_active' => true], ['is_active' => false, 'archived' => true]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Das Konto wurde archiviert. Die Vorgänge und Protokolleinträge des Benutzers bleiben erhalten.');
    }

    public function restore(int $userId): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($userId);
        $user->restore();

        AuditService::log('admin.users.restored', $user, ['archived' => true], ['archived' => false]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Das Konto wurde wiederhergestellt. Es ist noch deaktiviert und muss aktiviert werden.');
    }

    /**
     * Erzeugt einen zeitlich begrenzten Link zum Setzen des Passworts und
     * versendet die Zugangsdaten. Es wird nie ein Passwort im Klartext
     * versendet.
     */
    private function dispatchCredentials(User $user, ?string $note = null): array
    {
        $user->loadMissing('roles');

        $token = Password::createToken($user);
        $url = route('password.reset', ['token' => $token]).'?email='.urlencode($user->email);

        return MailDispatcher::send(
            $user->email,
            new UserCredentialsMail($user, $url, $note),
            'admin.users.credentials_sent',
            $user,
        );
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Das eigene Konto kann nicht deaktiviert werden.');

        $user->update(['is_active' => false]);
        AuditService::log('admin.users.deactivated', $user, ['is_active' => true], ['is_active' => false]);

        return back()->with('success', 'Der Benutzer wurde deaktiviert.');
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['is_active' => true]);
        AuditService::log('admin.users.activated', $user, ['is_active' => false], ['is_active' => true]);

        return back()->with('success', 'Der Benutzer wurde aktiviert.');
    }
}
