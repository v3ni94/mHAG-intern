@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <x-page-header :title="$user->name" label="Benutzer">
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Bearbeiten</a>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Konto</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">E-Mail</dt><dd class="col-7">{{ $user->email }}</dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">
                            @if ($user->is_active)<x-status-badge severity="success" label="Aktiv" />@else<x-status-badge severity="danger" label="Deaktiviert" />@endif
                        </dd>
                        <dt class="col-5">Rollen</dt>
                        <dd class="col-7">{{ $user->roles->pluck('name')->implode(', ') ?: 'keine' }}</dd>
                        <dt class="col-5">Zwei-Faktor-Authentifizierung</dt>
                        <dd class="col-7">
                            @if ($user->hasTwoFactorEnabled())<x-status-badge severity="success" label="Eingerichtet" />@else<x-status-badge severity="neutral" label="Nicht eingerichtet" />@endif
                        </dd>
                        <dt class="col-5">Zugeordnete Entität</dt>
                        <dd class="col-7">{{ $user->entity?->display_name ?? 'keine' }}</dd>
                        <dt class="col-5">Letzter Login</dt>
                        <dd class="col-7">{{ $user->last_login_at ? format_datetime($user->last_login_at) : 'noch nie' }}</dd>
                        <dt class="col-5">Angelegt am</dt>
                        <dd class="col-7">{{ format_datetime($user->created_at) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Datenbereich</div>
                @if ($user->entityAssignments->isNotEmpty())
                    <ul class="list-group list-group-flush">
                        @foreach ($user->entityAssignments as $assignment)
                            <li class="list-group-item small d-flex justify-content-between">
                                <span>
                                    {{ $assignment->entity?->display_name ?? ('Entität #'.$assignment->entity_id) }}
                                    @if ($assignment->label) <span class="text-muted">({{ $assignment->label }})</span> @endif
                                </span>
                                <span class="text-muted">{{ $assignment->context }}@if ($assignment->is_default) · Standard @endif</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="card-body"><x-empty-state icon="bi-diagram-2" message="Keine Entitäten zugeordnet." /></div>
                @endif
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">Letzte Anmeldeversuche</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Zeitpunkt</th><th>IP-Adresse</th><th>Ergebnis</th></tr></thead>
                        <tbody>
                            @forelse ($lastLogins as $attempt)
                                <tr>
                                    <td>{{ format_datetime($attempt->created_at) }}</td>
                                    <td>{{ $attempt->ip }}</td>
                                    <td>
                                        @if ($attempt->successful)<x-status-badge severity="success" label="Erfolgreich" />@else<x-status-badge severity="danger" label="Fehlgeschlagen" />@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted small">Keine Anmeldeversuche protokolliert.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
