@extends('layouts.app')

@section('title', 'Benutzer')

@section('content')
    <x-page-header title="Benutzerverwaltung" label="Administration">
        <a href="{{ route('admin.invitations.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-envelope"></i> Einladungen
        </a>
        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Benutzer anlegen
        </a>
    </x-page-header>

    <form method="GET" class="mb-3">
        <div class="input-group input-group-sm" style="max-width: 360px;">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name oder E-Mail" aria-label="Suche">
            <button class="btn btn-outline-secondary">Suchen</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Rollen</th>
                        <th>2FA</th>
                        <th>Datenbereich</th>
                        <th>Letzter Login</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><a href="{{ route('admin.users.show', $user) }}" class="text-decoration-none fw-semibold">{{ $user->name }}</a></td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="badge text-bg-light border">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($user->hasTwoFactorEnabled())
                                    <x-status-badge severity="success" label="Aktiv" />
                                @else
                                    <x-status-badge severity="neutral" label="Nicht eingerichtet" />
                                @endif
                            </td>
                            <td class="small">{{ $user->entity_assignments_count }} Zuordnung(en)</td>
                            <td class="small">{{ $user->last_login_at ? format_datetime($user->last_login_at) : 'noch nie' }}</td>
                            <td>
                                @if ($user->is_active)
                                    <x-status-badge severity="success" label="Aktiv" />
                                @else
                                    <x-status-badge severity="danger" label="Deaktiviert" />
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary" aria-label="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if ($user->is_active)
                                    <x-confirm-form :action="route('admin.users.deactivate', $user)"
                                                    confirm="Benutzer {{ $user->name }} wirklich deaktivieren?"
                                                    label="Deaktivieren" class="btn btn-sm btn-outline-danger" />
                                @else
                                    <x-confirm-form :action="route('admin.users.activate', $user)"
                                                    confirm="Benutzer {{ $user->name }} wieder aktivieren?"
                                                    label="Aktivieren" class="btn btn-sm btn-outline-success" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-people" message="Keine Benutzer gefunden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
