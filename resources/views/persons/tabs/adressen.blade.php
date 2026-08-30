{{-- Akte: Adressen (gemeinsam für Personen und Unternehmen, $routePrefix steuert die Routen) --}}
@php
    $prefix = $routePrefix ?? 'persons';
    $canEdit = auth()->user()->can($prefix === 'companies' ? 'companies.update' : 'persons.update');
    $typeOptions = \App\Http\Requests\MasterData\AddressRequest::typeOptions();
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Adressen</span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#address-create">
                <i class="bi bi-plus-lg"></i> Adresse hinzufügen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'address-create' ? 'show' : '' }}" id="address-create">
            <div class="card-body hairline-top">
                <form method="POST" action="{{ route($prefix.'.addresses.store', $entity) }}">
                    @csrf
                    <input type="hidden" name="_form" value="address-create">
                    @include('persons.tabs.partials.address-fields', ['address' => null, 'typeOptions' => $typeOptions])
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    @if ($entity->addresses->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-geo-alt" message="Keine Adressen hinterlegt." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Typ</th>
                    <th>Anschrift</th>
                    <th>Gültigkeit</th>
                    <th>Hauptadresse</th>
                    @if ($canEdit)<th class="text-end">Aktionen</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($entity->addresses as $address)
                    <tr>
                        <td>{{ $typeOptions[$address->type] ?? $address->type }}</td>
                        <td>
                            {{ $address->oneLine() }}
                            @if ($address->addition)<div class="text-muted small">{{ $address->addition }}</div>@endif
                            @if ($address->country && $address->country !== 'Deutschland')<div class="text-muted small">{{ $address->country }}</div>@endif
                        </td>
                        <td>
                            @if ($address->valid_from || $address->valid_until)
                                {{ format_date($address->valid_from) }} bis {{ format_date($address->valid_until) ?: 'offen' }}
                            @else
                                <span class="text-muted">Unbefristet</span>
                            @endif
                        </td>
                        <td>
                            @if ($address->is_primary)
                                <x-status-badge severity="info" icon="bi-star-fill" label="Hauptadresse" />
                            @endif
                        </td>
                        @if ($canEdit)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#address-edit-{{ $address->id }}" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <x-confirm-form :action="route($prefix.'.addresses.destroy', [$entity, $address])" method="DELETE"
                                                confirm="Diese Adresse wirklich löschen?" icon="bi-trash" label="" />
                            </td>
                        @endif
                    </tr>
                    @if ($canEdit)
                        <tr class="collapse" id="address-edit-{{ $address->id }}">
                            <td colspan="5">
                                <form method="POST" action="{{ route($prefix.'.addresses.update', [$entity, $address]) }}">
                                    @csrf
                                    @method('PUT')
                                    @include('persons.tabs.partials.address-fields', ['address' => $address, 'typeOptions' => $typeOptions])
                                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Aktualisieren</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
