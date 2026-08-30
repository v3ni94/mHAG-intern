@extends('layouts.app')

@section('title', 'Einladungen')

@section('content')
    <x-page-header title="Benutzereinladungen" label="Administration">
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Benutzer</a>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-header">Neue Einladung</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.invitations.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">E-Mail-Adresse *</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="entity_id">Zugeordnete Person / Unternehmen</label>
                        <select id="entity_id" name="entity_id" class="form-select @error('entity_id') is-invalid @enderror">
                            <option value="">Keine Zuordnung</option>
                            @foreach ($entities as $entity)
                                <option value="{{ $entity->id }}" @selected(old('entity_id') == $entity->id)>{{ $entity->display_name }}</option>
                            @endforeach
                        </select>
                        @error('entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label d-block">Rollen *</label>
                        @error('roles')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="row">
                            @foreach ($roles as $role)
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}"
                                               id="inv-role-{{ $role->id }}" @checked(in_array($role->name, old('roles', []), true))>
                                        <label class="form-check-label" for="inv-role-{{ $role->id }}">{{ $role->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="entity_ids">Datenbereich <x-help-icon text="Entitäten, deren Daten der eingeladene Benutzer sehen darf. Mehrfachauswahl mit Strg bzw. Cmd." /></label>
                        <select id="entity_ids" name="entity_ids[]" class="form-select @error('entity_ids') is-invalid @enderror" multiple size="8">
                            @foreach ($entities as $entity)
                                <option value="{{ $entity->id }}" @selected(in_array($entity->id, old('entity_ids', []), false))>{{ $entity->display_name }}</option>
                            @endforeach
                        </select>
                        @error('entity_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary"><i class="bi bi-envelope"></i> Einladung senden</button>
                    <span class="text-muted small ms-2">Der Link ist 7 Tage gültig und nur einmal verwendbar.</span>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Versendete Einladungen</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>E-Mail</th>
                        <th>Rollen</th>
                        <th>Eingeladen von</th>
                        <th>Gültig bis</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invitations as $invitation)
                        <tr>
                            <td>{{ $invitation->email }}</td>
                            <td class="small">{{ implode(', ', $invitation->roles ?? []) }}</td>
                            <td class="small">{{ $invitation->inviter?->name ?? 'unbekannt' }}</td>
                            <td class="small">{{ format_datetime($invitation->expires_at) }}</td>
                            <td>
                                @if ($invitation->accepted_at)
                                    <x-status-badge severity="success" label="Angenommen am {{ format_date($invitation->accepted_at) }}" />
                                @elseif ($invitation->revoked_at)
                                    <x-status-badge severity="neutral" label="Widerrufen" />
                                @elseif ($invitation->expires_at->isPast())
                                    <x-status-badge severity="warning" label="Abgelaufen" />
                                @else
                                    <x-status-badge severity="info" label="Offen" />
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if (! $invitation->accepted_at && ! $invitation->revoked_at)
                                    <x-confirm-form :action="route('admin.invitations.resend', $invitation)"
                                                    confirm="Einladung mit neuem Link erneut senden? Der alte Link wird ungültig."
                                                    label="Erneut senden" icon="bi-arrow-repeat" class="btn btn-sm btn-outline-secondary" />
                                    <x-confirm-form :action="route('admin.invitations.revoke', $invitation)"
                                                    confirm="Einladung wirklich widerrufen?"
                                                    label="Widerrufen" icon="bi-x-lg" class="btn btn-sm btn-outline-danger" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-envelope" message="Noch keine Einladungen versendet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $invitations->links() }}</div>
@endsection
