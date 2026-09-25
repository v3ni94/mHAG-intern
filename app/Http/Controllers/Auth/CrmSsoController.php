<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Services\AuditService;
use App\Services\Sso\CrmSsoService;
use App\Services\Sso\SsoException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Zentrale Anmeldung über das CRM (Abschnitt 3.4 des CRM-Masterprompts).
 *
 * Ablauf:
 *   1. start()     Zufallswerte erzeugen, in der Sitzung ablegen, zum CRM
 *                  weiterleiten
 *   2. CRM         prüft Kennwort und zweiten Faktor
 *   3. rueckkehr() Code gegen Token tauschen, Identitätstoken prüfen,
 *                  Benutzer zuordnen, anmelden
 *
 * Die Werte aus Schritt 1 sind einmalig und werden in Schritt 3 verbraucht.
 * Damit kann eine Antwort des CRM keiner anderen Anfrage untergeschoben
 * werden.
 */
class CrmSsoController extends Controller
{
    private const SITZUNG_STATE = 'sso:state';

    private const SITZUNG_NONCE = 'sso:nonce';

    private const SITZUNG_VERIFIER = 'sso:verifier';

    public function __construct(private readonly CrmSsoService $sso) {}

    public function start(Request $request): RedirectResponse
    {
        if (! $this->sso->istEingerichtet()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Die zentrale Anmeldung über das CRM ist nicht eingerichtet.',
            ]);
        }

        try {
            $vorbereitung = $this->sso->anmeldungVorbereiten();
        } catch (SsoException $e) {
            return $this->abbrechen($request, $e, null);
        }

        $request->session()->put(self::SITZUNG_STATE, $vorbereitung['state']);
        $request->session()->put(self::SITZUNG_NONCE, $vorbereitung['nonce']);
        $request->session()->put(self::SITZUNG_VERIFIER, $vorbereitung['verifier']);

        return redirect()->away($vorbereitung['url']);
    }

    public function rueckkehr(Request $request): RedirectResponse
    {
        // Die Zufallswerte gelten nur für diesen einen Vorgang.
        $state = (string) $request->session()->pull(self::SITZUNG_STATE, '');
        $nonce = (string) $request->session()->pull(self::SITZUNG_NONCE, '');
        $verifier = (string) $request->session()->pull(self::SITZUNG_VERIFIER, '');

        if ($request->filled('error')) {
            return $this->abbrechen($request, new SsoException(
                'Die Anmeldung über das CRM wurde abgebrochen.',
                'abbruch_crm',
                ['error' => substr((string) $request->query('error'), 0, 100)],
            ), null);
        }

        if ($state === '' || $verifier === '' || $nonce === '') {
            return $this->abbrechen($request, new SsoException(
                'Die Anmeldung ist abgelaufen. Bitte erneut beginnen.',
                'sitzung_leer',
            ), null);
        }

        if (! hash_equals($state, (string) $request->query('state', ''))) {
            return $this->abbrechen($request, new SsoException(
                'Die Antwort des CRM gehört nicht zu dieser Anmeldung.',
                'state',
            ), null);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->abbrechen($request, new SsoException(
                'Das CRM hat keinen Anmeldecode übermittelt.',
                'kein_code',
            ), null);
        }

        try {
            $angaben = $this->sso->codeEinloesen($code, $verifier, $nonce);
            $benutzer = $this->sso->benutzerFinden($angaben);
        } catch (SsoException $e) {
            return $this->abbrechen($request, $e, $e->protokoll['email'] ?? null);
        }

        LoginAttempt::create([
            'email' => $benutzer->email,
            'user_id' => $benutzer->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'successful' => true,
        ]);

        /*
         * Zweiter Faktor: Ist die Prüfung des CRM anerkannt (config sso.
         * trust_mfa), ist die Identität dort bereits mit zweitem Faktor
         * geprüft worden. Andernfalls verlangt das Intranet zusätzlich
         * seinen eigenen zweiten Faktor.
         */
        if (! config('sso.trust_mfa') && $benutzer->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor:user_id', $benutzer->id);
            $request->session()->put('two_factor:remember', false);
            $request->session()->put('sso:pending', true);

            AuditService::log('auth.sso_zweiter_faktor', $benutzer, [], [], [
                'grund' => 'Die Prüfung des CRM wird nicht anerkannt (sso.trust_mfa = false).',
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($benutzer);
        $request->session()->regenerate();
        $benutzer->forceFill(['last_login_at' => now()])->save();

        AuditService::log('auth.sso_login', $benutzer, [], [], [
            'aussteller' => (string) config('sso.issuer'),
            'zweiter_faktor' => config('sso.trust_mfa')
                ? 'im CRM geprüft'
                : 'zusätzlich im Intranet geprüft',
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Abbruch mit verständlicher Meldung. Einzelheiten gehen in Protokoll
     * und Prüfspur, nie auf den Bildschirm.
     */
    private function abbrechen(Request $request, SsoException $fehler, ?string $email): RedirectResponse
    {
        $request->session()->forget([self::SITZUNG_STATE, self::SITZUNG_NONCE, self::SITZUNG_VERIFIER]);

        if ($email !== null && $email !== '') {
            LoginAttempt::create([
                'email' => $email,
                'user_id' => null,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'successful' => false,
            ]);
        }

        AuditService::log('auth.sso_failed', null, [], [], [
            'grund' => $fehler->grund,
        ] + $fehler->protokoll);

        Log::warning('Zentrale Anmeldung fehlgeschlagen', [
            'grund' => $fehler->grund,
            'ip' => $request->ip(),
        ] + $fehler->protokoll);

        return redirect()->route('login')->withErrors(['email' => $fehler->getMessage()]);
    }
}
