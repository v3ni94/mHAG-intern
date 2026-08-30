@extends('layouts.app')
@section('title', 'Zwei-Faktor-Authentifizierung')
@section('content')
    <x-page-header title="Zwei-Faktor-Authentifizierung" label="Sicherheit" />

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    {{-- Hinterlegtes Geheimnis, das nicht gelesen werden kann. Tritt nach
                         einem Wechsel des Anwendungsschluessels auf. Es wird hier bewusst
                         KEIN neues Geheimnis erzeugt: das wuerde den bestehenden zweiten
                         Faktor ohne Nachweis ersetzen. --}}
                    @if ($geheimnisNichtLesbar ?? false)
                        <div class="alert alert-danger">
                            <strong>Das gespeicherte Geheimnis ist nicht lesbar.</strong>
                            <p class="mb-0 mt-2">Das tritt auf, wenn der Anwendungsschlüssel der
                            Installation gewechselt wurde. Die Zwei-Faktor-Anmeldung bleibt für Ihr
                            Konto bestehen, sie kann aber nicht geprüft werden. Bitte wenden Sie
                            sich an die Administration, dort wird die Zwei-Faktor-Anmeldung für Ihr
                            Konto zurückgesetzt. Danach richten Sie sie hier neu ein.</p>
                        </div>
                    @endif
                    @if ($user->hasTwoFactorEnabled())
                        <p>
                            <x-status-badge severity="success" label="2FA ist aktiv" />
                            <span class="text-muted small ms-2">aktiviert am {{ format_datetime($user->two_factor_confirmed_at) }}</span>
                        </p>
                        <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="d-inline">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm">Neue Recovery-Codes erzeugen</button>
                        </form>
                        @unless ($user->requiresTwoFactor())
                            <x-confirm-form :action="route('two-factor.disable')" method="DELETE"
                                confirm="Zwei-Faktor-Authentifizierung wirklich deaktivieren?"
                                label="2FA deaktivieren" class="btn btn-sm btn-outline-danger ms-2" />
                        @else
                            <p class="form-text mt-2">Für Ihre Rolle ist 2FA verpflichtend und kann nicht deaktiviert werden.</p>
                        @endunless
                    @elseif ($geheimnis === null)
                        <p class="mb-0 text-muted">Die Einrichtung ist derzeit nicht möglich.
                        Bitte wenden Sie sich an die Administration.</p>
                    @else
                        <p class="mb-2">Scannen Sie den QR-Code mit einer TOTP-App (Google Authenticator, Microsoft Authenticator, 1Password, Authy u. a.) und bestätigen Sie mit einem Code.</p>
                        <div class="d-flex flex-wrap align-items-start gap-4">
                            <div class="border rounded p-2 bg-white">{!! $qrSvg !!}</div>
                            <div>
                                <div class="versal-label">Manueller Schlüssel</div>
                                <code class="user-select-all">{{ $geheimnis }}</code>
                                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-3" style="max-width: 240px;">
                                    @csrf
                                    <label for="code" class="form-label required">Bestätigungscode</label>
                                    <input type="text" name="code" id="code" inputmode="numeric" maxlength="6" required
                                           class="form-control @error('code') is-invalid @enderror" autocomplete="one-time-code">
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <button type="submit" class="btn btn-primary mt-2 w-100">2FA aktivieren</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            @if ($recoveryCodes)
                <div class="card border-warning">
                    <div class="card-header bg-beige"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Recovery-Codes – nur jetzt sichtbar</div>
                    <div class="card-body">
                        <p class="small text-muted">Bewahren Sie diese Codes sicher auf. Jeder Code ist einmal verwendbar. Nach dem Verlassen dieser Seite werden sie nicht mehr angezeigt.</p>
                        <div class="row">
                            @foreach ($recoveryCodes as $code)
                                <div class="col-6"><code>{{ $code }}</code></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
