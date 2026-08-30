@extends('layouts.guest')
@section('title', 'Passwort vergessen')
@section('content')
    <h1 class="h5 mb-3">Passwort zurücksetzen</h1>
    <p class="text-muted small">Geben Sie Ihre E-Mail-Adresse ein. Sie erhalten einen Link zum Zurücksetzen des Passworts.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label required">E-Mail-Adresse</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100">Link anfordern</button>
        <div class="text-center mt-3"><a href="{{ route('login') }}" class="small">Zurück zur Anmeldung</a></div>
    </form>
@endsection
