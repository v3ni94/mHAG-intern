@props(['label', 'value', 'severity' => null, 'hint' => null, 'icon' => null])
<div {{ $attributes->merge(['class' => 'kpi-card'.($severity ? ' severity-'.$severity : '')]) }}>
    <div class="kpi-label">@if($icon)<i class="bi {{ $icon }} me-1"></i>@endif{{ $label }}</div>
    <div class="kpi-value">{{ $value }}</div>
    @if ($hint)<div class="kpi-hint">{{ $hint }}</div>@endif
</div>
