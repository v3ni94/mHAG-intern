@php
    /*
     * Tagesereignis in der Fußzeile (Abschnitt 119, erweitert am 30.08.2026):
     * für jeden Kalendertag ein Aktionstag, zum Beispiel der Welthundetag.
     * Angezeigt wird nur, was gepflegt und aktiv ist; ohne Eintrag bleibt die
     * Stelle leer. Der try/catch fängt den Zustand vor der Migration ab.
     */
    try {
        $tagesereignis = app(\App\Services\DailyEventService::class)->forDate();
        $event = $tagesereignis['event'];
        $weitere = $tagesereignis['others'];
    } catch (\Throwable $e) {
        $event = null;
        $weitere = collect();
    }
@endphp
@if ($event)
    @php
        $hinweis = trim((string) $event->description);
        if ($event->source) {
            $hinweis = ($hinweis !== '' ? $hinweis.' ' : '').'(Quelle: '.$event->source.')';
        }
        if ($weitere->isNotEmpty()) {
            $hinweis .= ' Weitere Tage heute: '.$weitere->pluck('title')->join(', ').'.';
        }
    @endphp
    <span title="{{ $hinweis }}">
        <i class="bi bi-calendar-heart text-gold"></i> Heute: {{ $event->title }}
        @if ($weitere->isNotEmpty())
            <span class="text-muted">und {{ $weitere->count() }} {{ $weitere->count() === 1 ? 'weiterer' : 'weitere' }}</span>
        @endif
    </span>
@endif
