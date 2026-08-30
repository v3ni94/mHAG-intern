@props(['text'])
{{-- Kontextbezogene Hilfe (Abschnitt 112) --}}
<i class="bi bi-question-circle text-muted" role="button" tabindex="0"
   data-bs-toggle="popover" data-bs-trigger="focus hover" data-bs-placement="top"
   data-bs-content="{{ $text }}" aria-label="Hilfe: {{ $text }}"></i>
