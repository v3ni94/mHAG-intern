{{-- Personenakte: Rollen / Organstellungen der Person --}}
@php($canEdit = auth()->user()->can('persons.update'))

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Organstellungen und Funktionen
            <x-help-icon text="Historische Organstellungen werden nie überschrieben. Beenden setzt das Enddatum, der Eintrag bleibt erhalten." />
        </span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#orgrole-create">
                <i class="bi bi-plus-lg"></i> Organstellung hinzufügen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'orgrole-create' ? 'show' : '' }}" id="orgrole-create">
            <div class="card-body hairline-top">
                @include('persons.tabs.partials.organization-role-create', [
                    'perspective' => 'person',
                    'prefix' => 'persons',
                    'companyOptions' => $companyOptions ?? collect(),
                ])
            </div>
        </div>
    @endif

    @if ($entity->organizationRolesAsPerson->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-person-badge" message="Keine Organstellungen hinterlegt." />
        </div>
    @else
        @include('persons.tabs.partials.organization-roles-table', [
            'roles' => $entity->organizationRolesAsPerson,
            'perspective' => 'person',
            'prefix' => 'persons',
            'canEdit' => $canEdit,
        ])
    @endif
</div>
