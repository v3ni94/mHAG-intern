<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pflegeoberfläche für den Changelog "Was ist neu?" (Abschnitt 118 Masterprompt).
 *
 * Felder: Version, Datum, Änderungen (Markdown). Es werden keine Beispiel-
 * einträge erzeugt; ohne gepflegten Eintrag bleibt die Seite leer.
 */
class ChangelogController extends Controller
{
    private const ATTRIBUTES = [
        'version' => 'Version',
        'released_on' => 'Datum',
        'changes' => 'Änderungen',
    ];

    public function index(): View
    {
        return view('admin.changelog.index', [
            'entries' => ChangelogEntry::query()
                ->orderByDesc('released_on')
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.changelog.create', ['entry' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $entry = ChangelogEntry::create($data);

        AuditService::log('admin.changelog.created', $entry, [], $entry->only(['version', 'released_on']));

        return redirect()->route('admin.changelog.index')
            ->with('success', 'Der Changelog-Eintrag wurde angelegt.');
    }

    public function edit(ChangelogEntry $changelog): View
    {
        return view('admin.changelog.edit', ['entry' => $changelog]);
    }

    public function update(Request $request, ChangelogEntry $changelog): RedirectResponse
    {
        $old = $changelog->only(['version', 'released_on', 'changes']);
        $changelog->update($this->validated($request));

        AuditService::log('admin.changelog.updated', $changelog, $old, $changelog->only(['version', 'released_on', 'changes']));

        return redirect()->route('admin.changelog.index')
            ->with('success', 'Der Changelog-Eintrag wurde aktualisiert.');
    }

    public function destroy(ChangelogEntry $changelog): RedirectResponse
    {
        AuditService::log('admin.changelog.deleted', $changelog, $changelog->only(['version', 'released_on']), []);
        $changelog->delete();

        return redirect()->route('admin.changelog.index')
            ->with('success', 'Der Changelog-Eintrag wurde gelöscht.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'version' => ['required', 'string', 'max:50'],
            'released_on' => ['required', 'date'],
            'changes' => ['required', 'string', 'max:20000'],
        ], [
            'version.required' => 'Bitte geben Sie die Versionsnummer an.',
            'released_on.required' => 'Bitte geben Sie das Datum der Veröffentlichung an.',
            'changes.required' => 'Bitte beschreiben Sie die Änderungen.',
        ], self::ATTRIBUTES);
    }
}
