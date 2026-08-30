<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt 2FA-Einrichtung für Rollen mit 2FA-Pflicht (Abschnitt 16).
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            if (! $request->routeIs('two-factor.*', 'logout', 'profile.*')) {
                return redirect()
                    ->route('two-factor.setup')
                    ->with('warning', 'Für Ihre Rolle ist die Zwei-Faktor-Authentifizierung verpflichtend. Bitte richten Sie diese jetzt ein.');
            }
        }

        return $next($request);
    }
}
