@props(['enum'])
@if ($enum)
    <x-status-badge :severity="method_exists($enum, 'severity') ? $enum->severity() : 'neutral'"
                    :icon="method_exists($enum, 'icon') ? $enum->icon() : null"
                    :label="$enum->label()" {{ $attributes }} />
@endif
