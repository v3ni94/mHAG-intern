@props(['amount', 'currency' => 'EUR'])
{{-- Einzige Wahrheit der Maskierung: format_money() (Abschnitt 126). --}}
@if (money_masking_active())
    <span class="text-muted" title="Datenschutzmodus aktiv">{{ format_money($amount, $currency) }}</span>
@else
    <span class="text-nowrap" style="font-variant-numeric: tabular-nums;">{{ format_money($amount, $currency) }}</span>
@endif
