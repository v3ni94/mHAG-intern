@extends('layouts.app')

@section('title', 'Benutzer bearbeiten')

@section('content')
    <x-page-header :title="'Benutzer bearbeiten: '.$user->name" label="Administration">
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header">Stammdaten</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name">Name *</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">E-Mail-Adresse *</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password">Neues Passwort <span class="text-muted small">(leer lassen, um es beizubehalten)</span></label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password_confirmation">Neues Passwort bestätigen</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="entity_id">Zugeordnete Person / Unternehmen</label>
                        <select id="entity_id" name="entity_id" class="form-select">
                            <option value="">Keine Zuordnung</option>
                            @foreach ($entities as $entity)
                                <option value="{{ $entity->id }}" @selected(old('entity_id', $user->entity_id) == $entity->id)>{{ $entity->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   @checked(old('is_active', $user->is_active ? '1' : '0') === '1')>
                            <label class="form-check-label" for="is_active">Konto aktiv</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Rollen</div>
            <div class="card-body">
                @error('roles')<div class="text-danger small">{{ $message }}</div>@enderror
                <div class="row">
                    @foreach ($roles as $role)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}"
                                       id="role-{{ $role->id }}"
                                       @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->all()), true))>
                                <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Datenbereich (sichtbare Entitäten für externe Rollen)</div>
            <div class="card-body">
                <p class="text-muted small">
                    Externe Benutzer (Darlehensgeber, Darlehensnehmer, Aktionäre, Aufsichtsrat) sehen ausschließlich
                    Datensätze der hier zugeordneten Entitäten. Leere Zeilen werden ignoriert; entfernte Zeilen werden gelöscht.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="assignments-table">
                        <thead>
                            <tr><th>Entität</th><th>Kontext</th><th>Bezeichnung</th><th>Standard</th></tr>
                        </thead>
                        <tbody>
                            @php($assignments = old('assignments', $user->entityAssignments->map(fn ($a) => ['entity_id' => $a->entity_id, 'context' => $a->context, 'label' => $a->label, 'is_default' => $a->is_default])->all()))
                            @foreach (array_merge($assignments, [['entity_id' => null, 'context' => 'self', 'label' => null, 'is_default' => false]]) as $i => $assignment)
                                <tr>
                                    <td style="min-width: 220px;">
                                        <select name="assignments[{{ $i }}][entity_id]" class="form-select form-select-sm">
                                            <option value="">Keine (Zeile ignorieren)</option>
                                            @foreach ($entities as $entity)
                                                <option value="{{ $entity->id }}" @selected(($assignment['entity_id'] ?? null) == $entity->id)>{{ $entity->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="assignments[{{ $i }}][context]" class="form-select form-select-sm">
                                            <option value="self" @selected(($assignment['context'] ?? 'self') === 'self')>Selbst</option>
                                            <option value="company" @selected(($assignment['context'] ?? '') === 'company')>Unternehmen</option>
                                            <option value="supervisory_board" @selected(($assignment['context'] ?? '') === 'supervisory_board')>Aufsichtsrat</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="assignments[{{ $i }}][label]" value="{{ $assignment['label'] ?? '' }}" class="form-control form-control-sm" placeholder="z. B. Als Darlehensgeber"></td>
                                    <td class="text-center">
                                        <input type="hidden" name="assignments[{{ $i }}][is_default]" value="0">
                                        <input type="checkbox" name="assignments[{{ $i }}][is_default]" value="1" class="form-check-input" @checked($assignment['is_default'] ?? false)>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="notify_user" value="1" id="notify_user"
                   @checked(old('notify_user'))>
            <label class="form-check-label" for="notify_user">
                Benutzer über Änderungen an Rollen, Anmeldeadresse oder Kontostatus per E-Mail informieren
            </label>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
