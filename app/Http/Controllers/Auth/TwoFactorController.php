<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Einrichtungsseite: QR-Code + manueller Secret Key (Abschnitt 16).
     */
    public function setup(Request $request): \Illuminate\View\View
    {
        $user = $request->user();

        if (! $user->two_factor_secret || $user->two_factor_confirmed_at) {
            if (! $user->two_factor_confirmed_at) {
                $user->forceFill([
                    'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
                ])->save();
            }
        }

        $qrSvg = null;
        if (! $user->two_factor_confirmed_at) {
            $otpUrl = app(Google2FA::class)->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->two_factor_secret,
            );
            $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd);
            $qrSvg = (new Writer($renderer))->writeString($otpUrl);
        }

        return view('auth.two-factor-setup', [
            'user' => $user,
            'qrSvg' => $qrSvg,
            'recoveryCodes' => session('two_factor:plain_recovery_codes'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (! app(Google2FA::class)->verifyKey($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'Der Bestätigungscode ist ungültig. Bitte prüfen Sie die Einrichtung in Ihrer Authenticator-App.',
            ]);
        }

        $plainCodes = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plainCodes),
        ])->save();

        AuditService::log('auth.two_factor_enabled', $user);

        // Klartext-Codes nur einmalig anzeigen, danach existieren nur Hashes.
        return redirect()->route('two-factor.setup')
            ->with('two_factor:plain_recovery_codes', $plainCodes)
            ->with('success', 'Zwei-Faktor-Authentifizierung wurde aktiviert. Bewahren Sie die Recovery-Codes sicher auf, sie werden nur einmal angezeigt.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $plainCodes = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plainCodes),
        ])->save();

        AuditService::log('auth.recovery_codes_regenerated', $user);

        return redirect()->route('two-factor.setup')
            ->with('two_factor:plain_recovery_codes', $plainCodes)
            ->with('success', 'Neue Recovery-Codes wurden erzeugt. Alle bisherigen Codes sind ungültig.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->requiresTwoFactor()) {
            return back()->withErrors([
                'code' => 'Für Ihre Rolle ist die Zwei-Faktor-Authentifizierung verpflichtend und kann nicht deaktiviert werden.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        AuditService::log('auth.two_factor_disabled', $user);

        return redirect()->route('two-factor.setup')->with('success', 'Zwei-Faktor-Authentifizierung wurde deaktiviert.');
    }
}
