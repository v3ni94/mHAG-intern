<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'zentraleAnmeldung' => app(\App\Services\Sso\CrmSsoService::class)->istEingerichtet(),
            'oertlicheAnmeldung' => $this->oertlicheAnmeldungMoeglich(),
        ]);
    }

    /**
     * Ist die zentrale Anmeldung über das CRM eingerichtet, läuft die
     * Anmeldung dort. Die örtliche Anmeldung mit Kennwort bleibt als
     * Notzugang bestehen, damit die Administration bei einer Störung des CRM
     * handlungsfähig ist (Entscheidung Betreiber, 25.09.2026).
     */
    private function oertlicheAnmeldungMoeglich(): bool
    {
        if (! app(\App\Services\Sso\CrmSsoService::class)->istEingerichtet()) {
            return true;
        }

        return (bool) config('sso.allow_local_login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->oertlicheAnmeldungMoeglich()) {
            AuditService::log('auth.notanmeldung_abgelehnt', null, [], [], [
                'email' => $credentials['email'],
            ]);

            throw ValidationException::withMessages([
                'email' => 'Die Anmeldung erfolgt zentral über das CRM.',
            ]);
        }

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        // Brute-Force-Schutz: 5 Versuche, danach temporäre Sperre (Abschnitt 17).
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Zu viele fehlgeschlagene Anmeldeversuche. Bitte warten Sie {$seconds} Sekunden.",
            ]);
        }

        $user = User::withTrashed()->where('email', $credentials['email'])->first();

        $valid = $user
            && ! $user->trashed()
            && $user->is_active
            && $user->password
            && Hash::check($credentials['password'], $user->password);

        LoginAttempt::create([
            'email' => $credentials['email'],
            'user_id' => $user?->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'successful' => (bool) $valid,
        ]);

        if (! $valid) {
            RateLimiter::hit($throttleKey, 300);
            AuditService::log('auth.login_failed', null, [], [], ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => 'Die Anmeldedaten sind nicht korrekt.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        /*
         * Notzugang: Ist die zentrale Anmeldung eingerichtet, ist jede
         * örtliche Anmeldung eine Ausnahme. Sie bleibt Administratoren
         * vorbehalten und wird gesondert protokolliert.
         */
        if (app(\App\Services\Sso\CrmSsoService::class)->istEingerichtet()) {
            if (! $user->hasRole('Administrator')) {
                AuditService::log('auth.notanmeldung_abgelehnt', $user, [], [], [
                    'grund' => 'Nur Administratoren duerfen den Notzugang verwenden.',
                ]);

                throw ValidationException::withMessages([
                    'email' => 'Die Anmeldung erfolgt zentral über das CRM.',
                ]);
            }

            AuditService::log('auth.notanmeldung', $user, [], [], [
                'hinweis' => 'Örtliche Anmeldung mit Kennwort trotz eingerichteter zentraler Anmeldung.',
            ]);
        }

        // 2FA aktiv: Anmeldung erst nach erfolgreicher Challenge.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor:user_id', $user->id);
            $request->session()->put('two_factor:remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditService::log('auth.login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditService::log('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
