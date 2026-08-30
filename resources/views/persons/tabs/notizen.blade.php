{{-- Personenakte: interne Notizen (nur für interne Rollen sichtbar) --}}
@if (auth()->user()->isInternal())
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Interne Notizen
                <x-help-icon text="Nur für interne Rollen sichtbar, nie für externe Benutzer" />
            </span>
            @can('persons.update')
                <a href="{{ route('persons.edit', $entity) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Bearbeiten
                </a>
            @endcan
        </div>
        <div class="card-body">
            @if ($entity->notes)
                <div style="white-space: pre-wrap;">{{ $entity->notes }}</div>
            @else
                <x-empty-state icon="bi-sticky" message="Keine internen Notizen vorhanden." />
            @endif
        </div>
    </div>
@endif
