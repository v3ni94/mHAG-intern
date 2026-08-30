@props(['amount', 'currency' => 'EUR'])
@if (auth()->user()?->privacy_mode)
    <span class="text-muted" title="Datenschutzmodus aktiv">•••••• €</span>
@else
    <span class="text-nowrap" style="font-variant-numeric: tabular-nums;">{{ format_money($amount, $currency) }}</span>
@endif
