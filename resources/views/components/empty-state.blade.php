@props(['icon' => 'bi-inbox', 'message' => 'Keine Einträge vorhanden.'])
<div class="empty-state">
    <i class="bi {{ $icon }}"></i>
    <div class="mt-2">{{ $message }}</div>
    @if (trim($slot))<div class="mt-2">{{ $slot }}</div>@endif
</div>
