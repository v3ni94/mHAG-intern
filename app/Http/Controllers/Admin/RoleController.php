<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\RoleRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Rollen und Berechtigungen (Abschnitt 15 Masterprompt).
 * Standardrollen: Namen unveränderlich, nicht löschbar; Berechtigungen
 * bleiben administrierbar.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get(),
            'systemRoles' => RoleRequest::SYSTEM_ROLES,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'groups' => $this->permissionGroups(),
            'role' => null,
            'isSystemRole' => false,
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->input('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->input('permissions', []));

        AuditService::log('admin.roles.created', $role, [], [
            'name' => $role->name,
            'permissions' => $request->input('permissions', []),
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Die Rolle wurde angelegt.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'groups' => $this->permissionGroups(),
            'role' => $role,
            'isSystemRole' => in_array($role->name, RoleRequest::SYSTEM_ROLES, true),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $old = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
        ];

        // Standardrollen behalten ihren Namen (Abschnitt 15)
        if (! in_array($role->name, RoleRequest::SYSTEM_ROLES, true)) {
            $role->update(['name' => $request->input('name')]);
        }
        $role->syncPermissions($request->input('permissions', []));

        AuditService::log('admin.roles.updated', $role, $old, [
            'name' => $role->name,
            'permissions' => $request->input('permissions', []),
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Die Rolle wurde aktualisiert.');
    }

    /**
     * Berechtigungen gruppiert nach Präfix (persons, loans, admin, ...).
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    private function permissionGroups(): Collection
    {
        return Permission::query()->orderBy('name')->get()
            ->groupBy(fn (Permission $p) => explode('.', $p->name)[0]);
    }
}
