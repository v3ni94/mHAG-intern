<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kontextwechsel (Abschnitt 13): Benutzer mit mehreren Rollen/Organisationen
 * wechseln die Ansicht; die Datenansicht wird entsprechend gefiltert.
 */
class ContextController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignment_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        // Im Ausschlussmodus sind die Zuordnungen Ausschluesse. Ein Wechsel in
        // eine ausgeschlossene Gesellschaft waere ein Widerspruch und wird
        // deshalb serverseitig verweigert (Anforderung 30.08.2026).
        if ($user->usesEntityExclusion()) {
            return back()->with('danger', 'Für dieses Konto ist kein Kontextwechsel vorgesehen.');
        }

        $assignment = $user->entityAssignments()
            ->whereKey($validated['assignment_id'])
            ->firstOrFail();

        session(['context_assignment_id' => $assignment->id]);

        return back()->with('success', 'Ansicht gewechselt: '.($assignment->label ?: $assignment->entity?->display_name));
    }
}
