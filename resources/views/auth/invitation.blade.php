@extends('layouts.guest')
@section('title', 'Einladung annehmen')
@section('content')
    <h1 class="h5 mb-1">Willkommen im Intranet</h1>
    <p class="text-muted small">Einladung für <strong>{{ $invitation->email }}</strong>. Vergeben Sie Ihr persönliches Passwort, um das Konto zu aktivieren.</p>
    <form method="POST" action="{{ route('invitations.accept', $token) }}">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label required">Ihr Name</label>
            <input type="text" name="name" id="name" required class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $invitation->entity?->display_name) }}">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label required">Passwort</label>
            <input type="password" name="password" id="password" required class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">Mindestens 12 Zeichen, Buchstaben und Zahlen.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label required">Passwort wiederholen</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required class="form-control">
        </div>
        <button type="submit" class="btn btn-primary w-100">Konto aktivieren</button>
        <p class="form-text mt-2">Im nächsten Schritt richten Sie die Zwei-Faktor-Authentifizierung ein.</p>
    </form>
@endsection
