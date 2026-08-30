<?php

namespace App\Http\Controllers;

use App\Models\ChangelogEntry;
use App\Models\FaqEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        'datenimport' => 'Datenimport aus Dateien (nicht implementiert)',
    ];

    /** Häufige Füllwörter, die als Suchbegriff nichts eingrenzen. */
    private const STOPWORDS = [
        'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'eines',
        'und', 'oder', 'aber', 'wie', 'was', 'wer', 'wem', 'wen', 'wo', 'ist', 'sind', 'war',
        'ich', 'sie', 'wir', 'mit', 'von', 'für', 'auf', 'bei', 'aus', 'dass', 'als', 'auch',
        'kann', 'man', 'zum', 'zur', 'ohne', 'sich', 'dem', 'beim', 'nach', 'vor', 'über',
    ];

    /** Länge des Kontextauszugs je Treffer. */
    private const EXCERPT_LENGTH = 180;

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

    /**
     * Volltextsuche (Abschnitt 115): sucht mit einzelnen Suchbegriffen über
     * Fragen und Antworten der FAQ sowie über Titel UND Inhalt der
     * Anleitungsseiten. Treffer werden nach Anzahl gefundener Begriffe
     * sortiert und mit Kontextauszug ausgegeben.
     */
    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $terms = $this->searchTerms($term);

        $faqResults = collect();
        $pageResults = collect();

        if ($terms !== []) {
            $faqResults = $this->searchFaq($request->user(), $terms);
            $pageResults = $this->searchPages($terms);
        }

        return view('help.search', [
            'term' => $term,
            'terms' => array_keys($terms),
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

    // ------------------------------------------------------------------
    // Suche
    // ------------------------------------------------------------------

    /**
     * Eingabe in einzelne Suchbegriffe zerlegen. Rückgabe:
     * Suchbegriff => Liste der Schreibvarianten (einfache Stammformen),
     * damit "bezahlt" auch "gezahlt" und "Zinsen" auch "Zinsausfall" findet.
     *
     * @return array<string, list<string>>
     */
    private function searchTerms(string $input): array
    {
        $raw = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($input)) ?: [];
        $terms = [];

        foreach ($raw as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            if (! array_key_exists($word, $terms)) {
                $terms[$word] = $this->variants($word);
            }
        }

        return $terms;
    }

    /**
     * Einfache Stammformen ohne Wörterbuch: Vorsilben ge/be und häufige
     * Endungen werden zusätzlich als Suchvariante geführt.
     *
     * @return list<string>
     */
    private function variants(string $word): array
    {
        $variants = [$word];

        foreach (['ge', 'be'] as $prefix) {
            if (str_starts_with($word, $prefix) && mb_strlen($word) - mb_strlen($prefix) >= 4) {
                $variants[] = mb_substr($word, mb_strlen($prefix));
            }
        }

        foreach (['ungen', 'enden', 'end', 'ung', 'en', 'em', 'er', 'es', 'e', 'n', 's'] as $suffix) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= 4) {
                $variants[] = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
                break;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param  array<string, list<string>>  $terms
     */
    private function searchFaq(User $user, array $terms): Collection
    {
        $entries = $this->visibleFaq($user)
            ->where(function ($query) use ($terms) {
                foreach ($terms as $variants) {
                    foreach ($variants as $variant) {
                        $like = '%'.$this->escapeLike($variant).'%';
                        $query->orWhere('question', 'like', $like)
                            ->orWhere('answer', 'like', $like);
                    }
                }
            })
            ->orderBy('sort')
            ->limit(200)
            ->get();

        // FAQ-Einträge sind kurz: hier genügt ein gefundener Suchbegriff.
        return $entries
            ->map(fn (FaqEntry $entry) => [
                'entry' => $entry,
                'matches' => $this->score($entry->question.' '.$entry->answer, $terms),
                'score' => 2 * $this->score($entry->question, $terms) + $this->score($entry->answer, $terms),
                'excerpt' => $this->excerpt($entry->answer, $terms),
            ])
            ->filter(fn (array $row) => $row['matches'] >= 1)
            ->sortByDesc('score')
            ->take(25)
            ->values();
    }

    /**
     * @param  array<string, list<string>>  $terms
     */
    private function searchPages(array $terms): Collection
    {
        $minimum = $this->minimumMatches($terms);

        return collect(self::PAGES)
            ->map(function (string $title, string $slug) use ($terms) {
                $content = $this->pageText($slug);

                return [
                    'slug' => $slug,
                    'title' => $title,
                    'matches' => $this->score($title.' '.$content, $terms),
                    'score' => 3 * $this->score($title, $terms) + $this->score($content, $terms),
                    'excerpt' => $this->excerpt($content, $terms),
                ];
            })
            ->filter(fn (array $row) => $row['matches'] >= $minimum)
            ->sortByDesc('score')
            ->take(25)
            ->values();
    }

    /**
     * Mindestanzahl gefundener Suchbegriffe je Anleitungsseite. Anleitungen
     * sind lange Texte; bei mehreren Begriffen muss mindestens die Hälfte
     * vorkommen, damit nicht ein einzelnes Füllwort jede Seite als Treffer
     * ausgibt.
     *
     * @param  array<string, list<string>>  $terms
     */
    private function minimumMatches(array $terms): int
    {
        return max(1, (int) ceil(count($terms) / 2));
    }

    /**
     * Anzahl der Suchbegriffe, die im Text (oder als Stammform) vorkommen.
     *
     * @param  array<string, list<string>>  $terms
     */
    private function score(?string $haystack, array $terms): int
    {
        if ($haystack === null || $haystack === '') {
            return 0;
        }
        $haystack = mb_strtolower($haystack);
        $hits = 0;

        foreach ($terms as $variants) {
            foreach ($variants as $variant) {
                if (mb_strpos($haystack, $variant) !== false) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits;
    }

    /**
     * Kontextauszug rund um den ersten Treffer.
     *
     * @param  array<string, list<string>>  $terms
     */
    private function excerpt(?string $text, array $terms): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
        if ($text === '') {
            return '';
        }

        $lower = mb_strtolower($text);
        $position = null;
        foreach ($terms as $variants) {
            foreach ($variants as $variant) {
                $found = mb_strpos($lower, $variant);
                if ($found !== false && ($position === null || $found < $position)) {
                    $position = $found;
                }
            }
        }

        if ($position === null) {
            return mb_strlen($text) > self::EXCERPT_LENGTH
                ? mb_substr($text, 0, self::EXCERPT_LENGTH).' …'
                : $text;
        }

        $start = max(0, $position - 60);
        $excerpt = mb_substr($text, $start, self::EXCERPT_LENGTH);

        return ($start > 0 ? '… ' : '').trim($excerpt).($start + self::EXCERPT_LENGTH < mb_strlen($text) ? ' …' : '');
    }

    /**
     * Reiner Text einer Anleitungsseite. Die Seiten sind statische Blade-
     * Dateien ohne Variablen; das Ergebnis wird je Prozess gemerkt.
     */
    private function pageText(string $slug): string
    {
        static $cache = [];

        if (! array_key_exists($slug, $cache)) {
            try {
                $html = view('help.pages.'.$slug)->render();
            } catch (\Throwable) {
                $cache[$slug] = '';

                return '';
            }

            $html = str_replace(['<', '>'], [' <', '> '], $html);
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cache[$slug] = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        }

        return $cache[$slug];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
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
