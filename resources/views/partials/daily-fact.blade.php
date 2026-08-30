@php
    // "Wussten Sie?" (Abschnitt 119): nur verifizierte, gepflegte Einträge.
    // Wenn kein Eintrag für heute existiert, wird nichts angezeigt.
    try {
        $fact = \App\Models\DailyFact::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('recurring', true)->where('month_day', now()->format('m-d'));
                })->orWhere('specific_date', now()->toDateString());
            })
            ->first();
    } catch (\Throwable $e) {
        $fact = null;
    }
@endphp
@if ($fact)
    <span title="{{ $fact->description }} (Quelle: {{ $fact->source }})">
        <i class="bi bi-lightbulb text-gold"></i> Wussten Sie? Heute ist {{ $fact->title }}.
    </span>
@endif
