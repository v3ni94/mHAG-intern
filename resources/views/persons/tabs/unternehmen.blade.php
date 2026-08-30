{{-- Personenakte: Unternehmen, mit denen die Person verbunden ist (über Organstellungen) --}}
@php
    $companies = $entity->organizationRolesAsPerson
        ->filter(fn ($r) => $r->company !== null)
        ->groupBy('company_entity_id');
@endphp

<div class="card">
    <div class="card-header">Verbundene Unternehmen</div>
    @if ($companies->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-building" message="Keine Unternehmensverbindungen vorhanden." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Unternehmen</th>
                    <th>Funktionen</th>
                    <th>Aktiv</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($companies as $companyRoles)
                    @php($company = $companyRoles->first()->company)
                    <tr>
                        <td>
                            <a href="{{ route('companies.show', $company) }}" class="fw-semibold text-decoration-none">
                                {{ $company->display_name }}
                            </a>
                        </td>
                        <td>
                            @foreach ($companyRoles as $role)
                                <span class="me-2">
                                    {{ $role->role->label() }}{{ $role->is_active ? '' : ' (beendet '.format_date($role->ended_on).')' }}
                                </span>
                            @endforeach
                        </td>
                        <td>
                            @if ($companyRoles->contains(fn ($r) => $r->is_active))
                                <x-status-badge severity="success" icon="bi-check-circle-fill" label="Aktiv" />
                            @else
                                <x-status-badge severity="neutral" icon="bi-clock-history" label="Nur Historie" />
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
