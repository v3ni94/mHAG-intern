{{-- Unternehmensakte: Stammdaten (Abschnitt 7) --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Stammdaten</span>
        @can('companies.update')
            <a href="{{ route('companies.edit', $entity) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
        @endcan
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Firmenname</dt>
            <dd class="col-sm-9">{{ $entity->company?->name }}</dd>
            <dt class="col-sm-3">Kurzname</dt>
            <dd class="col-sm-9">{{ $entity->company?->short_name ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Rechtsform</dt>
            <dd class="col-sm-9">{{ $entity->company?->legal_form ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Gründungsdatum</dt>
            <dd class="col-sm-9">{{ format_date($entity->company?->founded_on) ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Handelsregister</dt>
            <dd class="col-sm-9">{{ $entity->company?->commercial_register ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Registernummer</dt>
            <dd class="col-sm-9">{{ $entity->company?->register_number ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Registergericht</dt>
            <dd class="col-sm-9">{{ $entity->company?->register_court ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Steuernummer</dt>
            <dd class="col-sm-9">{{ $entity->company?->tax_number ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Umsatzsteuer-ID</dt>
            <dd class="col-sm-9">{{ $entity->company?->vat_id ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Wirtschafts-ID</dt>
            <dd class="col-sm-9">{{ $entity->company?->business_id ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Website</dt>
            <dd class="col-sm-9">{{ $entity->company?->website ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">E-Mail</dt>
            <dd class="col-sm-9">{{ $entity->company?->email ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Telefon</dt>
            <dd class="col-sm-9">{{ $entity->company?->phone ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Fax</dt>
            <dd class="col-sm-9">{{ $entity->company?->fax ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Branche</dt>
            <dd class="col-sm-9">{{ $entity->company?->industry ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Interne Unternehmens-ID</dt>
            <dd class="col-sm-9">{{ $entity->internal_number }}</dd>
            <dt class="col-sm-3">Tags</dt>
            <dd class="col-sm-9">
                @if (is_array($entity->tags) && count($entity->tags))
                    @foreach ($entity->tags as $tag)
                        <span class="badge text-bg-light border me-1">{{ $tag }}</span>
                    @endforeach
                @else
                    <span class="text-muted">Keine Tags</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

{{-- Ergänzende Steuerdaten (Finanzamt etc.) über die gemeinsame Unterressource --}}
@include('persons.tabs.steuerdaten', ['routePrefix' => 'companies'])
