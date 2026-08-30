<?php

namespace App\Http\Controllers;

use App\Models\ChangelogEntry;
use App\Models\FaqEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hilfe, Anleitungen, FAQ und Changelog (Abschnitte 110-118 Masterprompt).
 */
class HelpController extends Controller
{
    /**
     * Whitelist der Anleitungsseiten (Abschnitt 110): slug => Titel.
     * Nur diese Slugs werden gerendert (kein Pfad aus Benutzereingaben).
     */
    public const PAGES = [
        'erste-schritte' => 'Erste Schritte',
        'personen-anlegen' => 'Personen anlegen',
        'unternehmen-anlegen' => 'Unternehmen anlegen',
        'benutzer-einladen' => 'Benutzer einladen',
        'darlehen-anlegen' => 'Darlehen anlegen',
        'historische-darlehen' => 'Historische Darlehen anlegen',
        'zahlungen-verwalten' => 'Zahlungen verwalten',
        'zinsausfaelle-erfassen' => 'Zinsausfälle erfassen',
        'teilzahlungen' => 'Teilzahlungen erfassen',
        'auszahlungen-korrigieren' => 'Auszahlungen korrigieren',
        'vertraege-erstellen' => 'Verträge erstellen',
        'dokumente-hochladen' => 'Dokumente hochladen',
        'aktionaere-verwalten' => 'Aktionäre verwalten',
        'aktien-uebertragen' => 'Aktien übertragen',
        'beschluesse-erstellen' => 'Beschlüsse erstellen',
        'digitale-signaturen' => 'Digitale Signaturen',
        'reports' => 'Reports und Exporte',
    ];

    public function index(Request $request): View
    {
        return view('help.index', [
            'pages' => self::PAGES,
            'faqCount' => $this->visibleFaq($request->user())->count(),
        ]);
    }

    public function page(string $slug): View
    {
        abort_unless(array_key_exists($slug, self::PAGES), 404);

        return view('help.page', [
            'slug' => $slug,
            'title' => self::PAGES[$slug],
            'pages' => self::PAGES,
        ]);
    }

    public function faq(Request $request): View
    {
        $entries = $this->visibleFaq($request->user())
            ->orderBy('category')
            ->orderBy('sort')
            ->get()
            ->groupBy(fn (FaqEntry $entry) => $entry->category ?: 'Allgemein');

        return view('help.faq', ['groups' => $entries]);
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $faqResults = collect();
        $pageResults = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $faqResults = $this->visibleFaq($request->user())
                ->where(fn ($q) => $q->where('question', 'like', $like)->orWhere('answer', 'like', $like))
                ->orderBy('sort')
                ->limit(50)
                ->get();

            $pageResults = collect(self::PAGES)
                ->filter(fn (string $title, string $slug) => mb_stripos($title, $term) !== false || mb_stripos($slug, str_replace(' ', '-', mb_strtolower($term))) !== false)
                ->map(fn (string $title, string $slug) => ['slug' => $slug, 'title' => $title])
                ->values();
        }

        return view('help.search', [
            'term' => $term,
            'faqResults' => $faqResults,
            'pageResults' => $pageResults,
        ]);
    }

    public function changelog(): View
    {
        return view('help.changelog', [
            'entries' => ChangelogEntry::query()->orderByDesc('released_on')->orderByDesc('id')->get(),
        ]);
    }

    /**
     * FAQ-Sichtbarkeit (Abschnitt 114): all immer; internal für interne Rollen;
     * admin nur Administrator; supervisory_board/lender/borrower je Rolle.
     */
    private function visibleFaq(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $visibilities = ['all'];
        if ($user->isInternal()) {
            $visibilities[] = 'internal';
        }
        if ($user->hasRole('Administrator')) {
            $visibilities[] = 'admin';
        }
        if ($user->hasAnyRole(['Aufsichtsratsvorsitzender', 'Aufsichtsratsmitglied'])) {
            $visibilities[] = 'supervisory_board';
        }
        if ($user->hasRole('Darlehensgeber')) {
            $visibilities[] = 'lender';
        }
        if ($user->hasRole('Darlehensnehmer')) {
            $visibilities[] = 'borrower';
        }

        return FaqEntry::query()->where('is_active', true)->whereIn('visibility', $visibilities);
    }
}
