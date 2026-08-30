<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): \Illuminate\View\View|RedirectResponse
    {
        if (! $request->session()->has('two_factor:user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor:user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);

        $throttleKey = 'two-factor|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'code' => "Zu viele Versuche. Bitte warten Sie {$seconds} Sekunden.",
            ]);
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $verified = false;

        if ($code = trim((string) $request->input('code'))) {
            $verified = (bool) app(Google2FA::class)->verifyKey($user->two_factor_secret, $code);
        } elseif ($recovery = trim((string) $request->input('recovery_code'))) {
            // Recovery Codes liegen ausschließlich gehasht vor (Abschnitt 16).
            $codes = $user->two_factor_recovery_codes ?? [];
            foreach ($codes as $index => $hashed) {
                if ($hashed !== null && Hash::check($recovery, $hashed)) {
                    $codes[$index] = null; // einmalig verwendbar
                    $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
                    $verified = true;
                    AuditService::log('auth.recovery_code_used', $user);
                    break;
                }
            }
        }

        if (! $verified) {
            RateLimiter::hit($throttleKey, 300);
            throw ValidationException::withMessages([
                'code' => 'Der Code ist ungültig.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('two_factor:remember', false);
        $request->session()->forget('two_factor:user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditService::log('auth.login', $user, [], [], ['two_factor' => true]);

        return redirect()->intended(route('dashboard'));
    }
}
