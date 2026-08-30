@extends('layouts.app')

@section('title', 'Benutzer anlegen')

@section('content')
    <x-page-header title="Benutzer anlegen" label="Administration">
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <div class="alert alert-info small">
        Empfohlener Weg für neue Benutzer ist die <a href="{{ route('admin.invitations.index') }}">Einladung per E-Mail</a>,
        bei der der Benutzer sein Passwort selbst vergibt. Die direkte Anlage ist für Sonderfälle gedacht.
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name">Name *</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">E-Mail-Adresse *</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password">Passwort * <x-help-icon text="Mindestens 12 Zeichen mit Buchstaben und Zahlen." /></label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password_confirmation">Passwort bestätigen *</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
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
                    <div class="col-12">
                        <label class="form-label d-block">Rollen *</label>
                        @error('roles')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="row">
                            @foreach ($roles as $role)
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}"
                                               id="role-{{ $role->id }}" @checked(in_array($role->name, old('roles', []), true))>
                                        <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', '1') === '1')>
                            <label class="form-check-label" for="is_active">Konto aktiv</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Benutzer anlegen</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
