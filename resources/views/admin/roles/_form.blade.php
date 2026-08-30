@php($groupLabels = [
    'dashboard' => 'Dashboard', 'persons' => 'Personen', 'companies' => 'Unternehmen',
    'loans' => 'Darlehen', 'payments' => 'Zahlungen', 'contracts' => 'Verträge',
    'documents' => 'Dokumente', 'shares' => 'Aktien', 'resolutions' => 'Beschlüsse',
    'reports' => 'Reports', 'admin' => 'Administration', 'help' => 'Hilfe',
])

<div class="mb-3" style="max-width: 420px;">
    <label class="form-label" for="name">Rollenname *</label>
    <input type="text" id="name" name="name" value="{{ old('name', $role?->name) }}"
           class="form-control @error('name') is-invalid @enderror"
           @if ($isSystemRole) readonly aria-describedby="name-hint" @endif required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($isSystemRole)
        <div class="form-text" id="name-hint">Standardrolle: Der Name kann nicht geändert werden.</div>
    @endif
</div>

@error('permissions')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
@php($selected = old('permissions', $role?->permissions?->pluck('name')->all() ?? []))

<div class="row g-3">
    @foreach ($groups as $prefix => $permissions)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header py-2">{{ $groupLabels[$prefix] ?? ucfirst($prefix) }}</div>
                <div class="card-body py-2">
                    @foreach ($permissions as $permission)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                   id="perm-{{ $permission->id }}" @checked(in_array($permission->name, $selected, true))>
                            <label class="form-check-label small" for="perm-{{ $permission->id }}">{{ $permission->name }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
