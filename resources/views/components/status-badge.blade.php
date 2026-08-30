@props(['severity' => 'neutral', 'icon' => null, 'label'])
@php
    $defaultIcons = [
        'danger' => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'success' => 'bi-check-circle-fill',
        'info' => 'bi-info-circle-fill',
        'neutral' => 'bi-dash-circle',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'status-badge severity-'.$severity]) }} title="{{ $label }}">
    <i class="bi {{ $icon ?: ($defaultIcons[$severity] ?? $defaultIcons['neutral']) }}" aria-hidden="true"></i>{{ $label }}
</span>
