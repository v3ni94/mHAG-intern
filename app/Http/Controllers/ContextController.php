<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kontextwechsel (Abschnitt 13): Benutzer, die für mehrere Gesellschaften
 * handeln, wechseln die Ansicht. Die Ansicht schränkt die Geschäftsvorgänge
 * auf die gewählte Gesellschaft ein.
 *
 * Wichtige Abgrenzung: Die Ansicht ist ein FILTER, keine Berechtigung. Sie
 * kann den Datenumfang nur verkleinern, niemals vergrößern. Der direkte
 * Aufruf eines Vorgangs bleibt zulässig, solange die Berechtigung besteht;
 * andernfalls würde ein Lesezeichen unerklärlich ins Leere führen.
 */
class ContextController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        // Der Wert kommt als Zahl (Zuordnung) oder als Wort ("all") aus dem
        // Formular. Beides wird als Text geprueft, damit die Auswertung
        // eindeutig bleibt.
        $request->merge(['assignment_id' => (string) $request->input('assignment_id')]);

        $validated = $request->validate([
            'assignment_id' => ['required', 'string', 'max:64'],
        ], [], ['assignment_id' => 'Ansicht']);

        $user = $request->user();

        // Im Ausschlussmodus sind die Zuordnungen Ausschlüsse. Ein Wechsel in
        // eine ausgeschlossene Gesellschaft wäre ein Widerspruch.
        if ($user->usesEntityExclusion()) {
            return back()->with('danger', 'Für dieses Konto ist kein Kontextwechsel vorgesehen.');
        }

        $wahl = (string) $validated['assignment_id'];

        if ($wahl === User::CONTEXT_ALL) {
            session([User::CONTEXT_SESSION_KEY => User::CONTEXT_ALL]);
            AuditService::log('context.switched', $user, [], ['view' => 'Gesamtansicht']);

            return back()->with('success', 'Ansicht gewechselt: Gesamtansicht.');
        }

        $assignment = $user->availableContexts()->firstWhere('id', (int) $wahl);
        if (! $assignment) {
            return back()->with('danger', 'Diese Ansicht steht für Ihr Konto nicht zur Verfügung.');
        }

        session([User::CONTEXT_SESSION_KEY => $assignment->id]);
        AuditService::log('context.switched', $user, [], [
            'view' => $assignment->viewLabel(),
            'entity_id' => $assignment->entity_id,
        ]);

        return back()->with('success', 'Ansicht gewechselt: '.$assignment->viewLabel().'.');
    }
}
