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

class LoginController extends Controller
{
    public function show(): \Illuminate\View\View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

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
