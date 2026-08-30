{{-- Tabelle der Organstellungen. $perspective: 'person' (zeigt Unternehmen) oder 'company' (zeigt Person). --}}
@php
    $roleOptions = \App\Enums\OrganizationRoleType::cases();
@endphp
<div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
        <thead>
        <tr>
            <th>{{ $perspective === 'person' ? 'Unternehmen' : 'Person' }}</th>
            <th>Rolle</th>
            <th>Beginn</th>
            <th>Ende</th>
            <th>Vertretung</th>
            <th>Status</th>
            @if ($canEdit)<th class="text-end">Aktionen</th>@endif
        </tr>
        </thead>
        <tbody>
        @foreach ($roles as $role)
            @php($other = $perspective === 'person' ? $role->company : $role->person)
            <tr class="{{ $role->is_active ? '' : 'text-muted' }}">
                <td>
                    @if ($other)
                        @php($otherRoute = $perspective === 'person' ? 'companies.show' : 'persons.show')
                        <a href="{{ route($otherRoute, $other) }}" class="text-decoration-none">{{ $other->display_name }}</a>
                    @endif
                </td>
                <td><x-enum-badge :enum="$role->role" /></td>
                <td>{{ format_date($role->started_on) }}</td>
                <td>{{ format_date($role->ended_on) }}</td>
                <td>
                    @if ($role->sole_representation)
                        Einzelvertretung
                    @elseif ($role->representation_rule)
                        {{ $role->representation_rule }}
                    @endif
                    @if ($role->exemption_181)
                        <div class="text-muted small">Befreiung von § 181 BGB (Information)</div>
                    @endif
                </td>
                <td>
                    @if ($role->is_active)
                        <x-status-badge severity="success" icon="bi-check-circle-fill" label="Aktiv" />
                    @else
                        <x-status-badge severity="neutral" icon="bi-clock-history" label="Beendet" />
                    @endif
                </td>
                @if ($canEdit)
                    <td class="text-end">
                        @if ($role->is_active)
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#orgrole-end-{{ $role->id }}" title="Organstellung beenden">
                                <i class="bi bi-stop-circle"></i> Beenden
                            </button>
                        @else
                            <span class="text-muted small">Historie</span>
                        @endif
                    </td>
                @endif
            </tr>
            @if ($role->note)
                <tr class="{{ $role->is_active ? '' : 'text-muted' }}">
                    <td colspan="{{ $canEdit ? 7 : 6 }}" class="small text-muted pt-0">Notiz: {{ $role->note }}</td>
                </tr>
            @endif
            @if ($canEdit && $role->is_active)
                <tr class="collapse" id="orgrole-end-{{ $role->id }}">
                    <td colspan="7">
                        <form method="POST" action="{{ route($prefix.'.organization-roles.end', [$entity, $role]) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Ende der Organstellung *</label>
                                <input type="date" name="ended_on" value="{{ old('ended_on', now()->toDateString()) }}"
                                       class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notiz</label>
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="z. B. Grund des Ausscheidens">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Organstellung beenden? Der Eintrag bleibt als Historie erhalten.');">
                                    <i class="bi bi-stop-circle"></i> Beenden
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>
</div>
