@extends('layouts.guest')
@section('title', 'Neues Passwort')
@section('content')
    <h1 class="h5 mb-3">Neues Passwort setzen</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label for="email" class="form-label required">E-Mail-Adresse</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label required">Neues Passwort</label>
            <input type="password" name="password" id="password" required class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">Mindestens 12 Zeichen, Buchstaben und Zahlen.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label required">Passwort wiederholen</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required class="form-control">
        </div>
        <button type="submit" class="btn btn-primary w-100">Passwort speichern</button>
    </form>
@endsection
