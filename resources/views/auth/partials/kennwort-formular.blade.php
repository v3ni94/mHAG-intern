{{-- Anmeldung mit E-Mail-Adresse und Kennwort. Wird sowohl als einziger Weg
     als auch als Notanmeldung neben der zentralen Anmeldung verwendet. --}}
@php($zweitrangig = $zweitrangig ?? false)
<form method="POST" action="{{ route('login.store') }}" class="{{ $zweitrangig ? 'mt-3' : '' }}">
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label required">E-Mail-Adresse</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required
               @unless ($zweitrangig) autofocus @endunless
               class="form-control @error('email') is-invalid @enderror" autocomplete="username">
        @unless ($zweitrangig)
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endunless
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
    <button type="submit" class="btn {{ $zweitrangig ? 'btn-outline-secondary' : 'btn-primary' }} w-100">
        Anmelden
    </button>
    <div class="text-center mt-3">
        <a href="{{ route('password.request') }}" class="small">Passwort vergessen?</a>
    </div>
</form>
