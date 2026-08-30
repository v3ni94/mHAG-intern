@props(['origin'])
{{-- Herkunft eines IST-Wertes (Abschnitt 25): immer sichtbar kennzeichnen. --}}
@if ($origin)
    <x-status-badge :severity="$origin->severity()" :icon="$origin->icon()" :label="$origin->label()" {{ $attributes }} />
@endif
