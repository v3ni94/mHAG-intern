{{-- Unternehmensakte: Organe und Funktionsträger (Abschnitt 7) --}}
@php($canEdit = auth()->user()->can('companies.update'))

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Organe und Funktionsträger
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
                    'perspective' => 'company',
                    'prefix' => 'companies',
                    'personOptions' => $personOptions ?? collect(),
                ])
            </div>
        </div>
    @endif

    @if ($entity->organizationRolesAsCompany->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-person-badge" message="Keine Organstellungen hinterlegt." />
        </div>
    @else
        @include('persons.tabs.partials.organization-roles-table', [
            'roles' => $entity->organizationRolesAsCompany,
            'perspective' => 'company',
            'prefix' => 'companies',
            'canEdit' => $canEdit,
        ])
    @endif
</div>
