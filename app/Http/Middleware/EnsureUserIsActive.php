<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Layout und Berechtigungsprüfungen greifen auf Rollen und
        // Kontext-Zuordnungen zu; hier zentral laden, damit
        // Model::preventLazyLoading keine Ausnahme wirft.
        $user?->loadMissing(['roles', 'entityAssignments.entity']);

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Dieses Benutzerkonto ist deaktiviert.',
            ]);
        }

        return $next($request);
    }
}
