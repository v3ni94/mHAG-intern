<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyFact;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Pflegeoberfläche für die Tagesereignisse in der Fußzeile (Abschnitt 119,
 * erweitert am 30.08.2026): je Kalendertag ein Aktionstag, zum Beispiel der
 * Welthundetag.
 *
 * Quelle ist Pflichtfeld (Abschnitt 140: keine erfundenen Angaben). Ohne
 * gepflegten, aktiven Eintrag zeigt die Fußzeile an dieser Stelle nichts. Die
 * Übersicht weist offene Tage aus, damit Lücken sichtbar sind und gezielt
 * ergänzt werden können.
 */
class DailyFactController extends Controller
{
    private const ATTRIBUTES = [
        'title' => 'Titel',
        'description' => 'Beschreibung',
        'source' => 'Quelle',
        'country' => 'Land',
        'month_day' => 'Monat und Tag',
        'specific_date' => 'Datum',
        'recurring' => 'Wiederkehrend',
        'is_active' => 'Aktiv',
    ];

    public function index(Request $request, \App\Services\DailyEventService $events): View
    {
        $entries = DailyFact::query()
            ->when($request->filled('monat'), fn ($q) => $q->where('month_day', 'like', $request->query('monat').'-%'))
            ->orderBy('month_day')
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString();

        return view('admin.daily-facts.index', [
            'entries' => $entries,
            'today' => now()->format('m-d'),
            'coverage' => $events->coverage(),
            'heute' => $events->forDate(),
            'monatsnamen' => \App\Services\DailyEventService::monatsnamen(),
        ]);
    }

    public function create(): View
    {
        return view('admin.daily-facts.create', ['entry' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entry = DailyFact::create($this->validated($request));

        AuditService::log('admin.daily-facts.created', $entry, [], $entry->only(['month_day', 'title', 'source']));

        return redirect()->route('admin.daily-facts.index')
            ->with('success', 'Der Eintrag wurde angelegt.');
    }

    public function edit(DailyFact $dailyFact): View
    {
        return view('admin.daily-facts.edit', ['entry' => $dailyFact]);
    }

    public function update(Request $request, DailyFact $dailyFact): RedirectResponse
    {
        $old = $dailyFact->only(['month_day', 'specific_date', 'title', 'description', 'source', 'country', 'recurring', 'is_active']);
        $dailyFact->update($this->validated($request));

        AuditService::log('admin.daily-facts.updated', $dailyFact, $old, $dailyFact->only(['month_day', 'specific_date', 'title', 'description', 'source', 'country', 'recurring', 'is_active']));

        return redirect()->route('admin.daily-facts.index')
            ->with('success', 'Der Eintrag wurde aktualisiert.');
    }

    public function destroy(DailyFact $dailyFact): RedirectResponse
    {
        AuditService::log('admin.daily-facts.deleted', $dailyFact, $dailyFact->only(['month_day', 'title', 'source']), []);
        $dailyFact->delete();

        return redirect()->route('admin.daily-facts.index')
            ->with('success', 'Der Eintrag wurde gelöscht.');
    }

    /**
     * Wiederkehrende Einträge werden über Monat und Tag geführt, einmalige über
     * ein konkretes Datum. month_day wird bei einmaligen Einträgen aus dem
     * Datum abgeleitet, damit die Anzeigelogik unverändert greift.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $recurring = $request->boolean('recurring');

        $validator = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'recurring' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'month_day' => [$recurring ? 'required' : 'nullable', 'string', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/'],
            'specific_date' => [$recurring ? 'nullable' : 'required', 'date'],
        ], [
            'title.required' => 'Bitte geben Sie einen Titel an.',
            'source.required' => 'Bitte geben Sie die Quelle an. Ohne belegte Quelle wird kein Eintrag angezeigt.',
            'month_day.required' => 'Bitte geben Sie Monat und Tag im Format MM-TT an.',
            'month_day.regex' => 'Bitte geben Sie Monat und Tag im Format MM-TT an, zum Beispiel 03-08.',
            'specific_date.required' => 'Bitte geben Sie das Datum an.',
        ], self::ATTRIBUTES);

        $validator->after(function (Validator $validator) use ($request, $recurring) {
            $monthDay = (string) $request->input('month_day', '');
            if ($recurring && preg_match('/^(\d{2})-(\d{2})$/', $monthDay, $matches) === 1) {
                // Schaltjahr als Bezug, damit der 29.02. zulässig bleibt.
                if (! checkdate((int) $matches[1], (int) $matches[2], 2024)) {
                    $validator->errors()->add('month_day', 'Diesen Tag gibt es im gewählten Monat nicht.');
                }
            }
        });

        $data = $validator->validate();

        if ($recurring) {
            $data['specific_date'] = null;
        } else {
            $data['month_day'] = \Illuminate\Support\Carbon::parse($data['specific_date'])->format('m-d');
        }

        $data['recurring'] = $recurring;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
