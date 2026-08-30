@extends('layouts.guest')
@section('title', 'Anmeldung')
@section('content')
    <h1 class="h5 mb-3">Anmeldung</h1>
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label required">E-Mail-Adresse</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                   class="form-control @error('email') is-invalid @enderror" autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label required">Passwort</label>
            <input type="password" name="password" id="password" required
                   class="form-control @error('password') is-invalid @enderror" autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="remember" id="remember" class="form-check-input">
            <label for="remember" class="form-check-label">Angemeldet bleiben</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Anmelden</button>
        <div class="text-center mt-3">
            <a href="{{ route('password.request') }}" class="small">Passwort vergessen?</a>
        </div>
    </form>
@endsection
