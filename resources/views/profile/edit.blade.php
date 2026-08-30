@extends('layouts.app')
@section('title', 'Profil')
@section('content')
    <x-page-header title="Mein Profil" label="Konto" />
    <div class="row g-3">
        <div class="col-lg-6">
            {{-- Profilbild (Anforderung 30.08.2026) --}}
            <div class="card mb-3" id="profilbild">
                <div class="card-header">Profilbild</div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <x-user-avatar :user="$user" :size="72" />
                        <div class="small text-muted">
                            Das Bild erscheint im Benutzermenü oben rechts.
                            Ohne hinterlegtes Bild wird das Firmenzeichen der Müller Holding AG angezeigt.<br>
                            Zulässig sind JPG, PNG und WebP bis 2 MB.
                        </div>
                    </div>
                    <form method="POST" action="{{ route('profile.avatar.store') }}" enctype="multipart/form-data" class="mb-2">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="avatar">Bilddatei auswählen</label>
                            <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp"
                                   class="form-control @error('avatar') is-invalid @enderror" required>
                            @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Bild speichern</button>
                    </form>
                    @if ($user->hasAvatar())
                        <x-confirm-form :action="route('profile.avatar.destroy')" method="DELETE"
                                        confirm="Profilbild wirklich entfernen? Danach erscheint wieder das Firmenzeichen."
                                        label="Bild entfernen" icon="bi-trash"
                                        class="btn btn-sm btn-outline-danger" />
                    @endif
                    <div class="form-text mt-2">
                        Die Datei wird außerhalb des öffentlichen Verzeichnisses gespeichert und nur nach Anmeldung
                        ausgeliefert.
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">Stammdaten</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label required" for="name">Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">E-Mail-Adresse</label>
                            <input type="email" id="email" class="form-control" value="{{ $user->email }}" disabled>
                            <div class="form-text">Die E-Mail-Adresse wird durch die Administration geändert.</div>
                        </div>
                        <button class="btn btn-primary">Speichern</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Passwort ändern</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label required" for="current_password">Aktuelles Passwort</label>
                            <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="password">Neues Passwort</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                            <div class="form-text">Mindestens 12 Zeichen, Buchstaben und Zahlen.</div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="password_confirmation">Neues Passwort wiederholen</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                        </div>
                        <button class="btn btn-primary">Passwort ändern</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Anmeldehistorie</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Zeitpunkt</th><th>IP</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($loginHistory as $attempt)
                                <tr>
                                    <td>{{ format_datetime($attempt->created_at) }}</td>
                                    <td>{{ $attempt->ip }}</td>
                                    <td>
                                        @if ($attempt->successful)
                                            <x-status-badge severity="success" label="Erfolgreich" />
                                        @else
                                            <x-status-badge severity="danger" label="Fehlgeschlagen" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Keine Einträge.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
