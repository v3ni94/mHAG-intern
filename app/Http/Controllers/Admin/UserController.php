<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\UserStoreRequest;
use App\Http\Requests\Organisation\UserUpdateRequest;
use App\Models\Entity;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $users = User::query()
            ->with('roles:id,name')
            ->withCount('entityAssignments')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->query('search').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
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

        return redirect()->route('admin.users.index')->with('success', 'Der Benutzer wurde angelegt.');
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

        return redirect()->route('admin.users.index')->with('success', 'Der Benutzer wurde aktualisiert.');
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
