@props(['title', 'label' => null])
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
        @if ($label)<div class="versal-label">{{ $label }}</div>@endif
        <h1>{{ $title }}</h1>
        <div class="gold-bar"></div>
    </div>
    @if (trim($slot))
        <div class="d-flex flex-wrap gap-2 align-items-center">{{ $slot }}</div>
    @endif
</div>
