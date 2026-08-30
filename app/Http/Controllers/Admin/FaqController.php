<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\FaqEntryRequest;
use App\Models\FaqEntry;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FAQ-Verwaltung (Abschnitt 114 Masterprompt): Einträge anlegen, bearbeiten,
 * sortieren, Sichtbarkeit steuern, deaktivieren.
 */
class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $entries = FaqEntry::query()
            ->when($request->filled('kategorie'), fn ($q) => $q->where('category', $request->query('kategorie')))
            ->orderBy('category')
            ->orderBy('sort')
            ->paginate(50)
            ->withQueryString();

        return view('admin.faq.index', [
            'entries' => $entries,
            'categories' => FaqEntry::query()->select('category')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(): View
    {
        return view('admin.faq.create', ['entry' => null]);
    }

    public function store(FaqEntryRequest $request): RedirectResponse
    {
        $entry = FaqEntry::create([
            ...$request->safe()->only(['category', 'question', 'answer', 'visibility']),
            'sort' => $request->integer('sort', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditService::log('admin.faq.created', $entry, [], $entry->only(['question', 'visibility']));

        return redirect()->route('admin.faq.index')->with('success', 'Der FAQ-Eintrag wurde angelegt.');
    }

    public function edit(FaqEntry $faq): View
    {
        return view('admin.faq.edit', ['entry' => $faq]);
    }

    public function update(FaqEntryRequest $request, FaqEntry $faq): RedirectResponse
    {
        $old = $faq->only(['category', 'question', 'answer', 'visibility', 'sort', 'is_active']);

        $faq->update([
            ...$request->safe()->only(['category', 'question', 'answer', 'visibility']),
            'sort' => $request->integer('sort', 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditService::log('admin.faq.updated', $faq, $old, $faq->only(['category', 'question', 'answer', 'visibility', 'sort', 'is_active']));

        return redirect()->route('admin.faq.index')->with('success', 'Der FAQ-Eintrag wurde aktualisiert.');
    }

    public function destroy(FaqEntry $faq): RedirectResponse
    {
        AuditService::log('admin.faq.deleted', $faq, $faq->only(['question', 'visibility']), []);
        $faq->delete();

        return redirect()->route('admin.faq.index')->with('success', 'Der FAQ-Eintrag wurde gelöscht.');
    }
}
