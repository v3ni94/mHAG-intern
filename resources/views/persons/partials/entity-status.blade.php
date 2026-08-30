{{-- Statusanzeige einer Entity (aktiv/archiviert), immer Icon + Text --}}
@if ($entity->status === 'archived')
    <x-status-badge severity="neutral" icon="bi-archive" label="Archiviert" />
@else
    <x-status-badge severity="success" icon="bi-check-circle-fill" label="Aktiv" />
@endif
