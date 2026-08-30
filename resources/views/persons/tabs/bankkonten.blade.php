{{-- Akte: Bankkonten (gemeinsam für Personen und Unternehmen) --}}
@php
    $prefix = $routePrefix ?? 'persons';
    $canEdit = auth()->user()->can($prefix === 'companies' ? 'companies.update' : 'persons.update');
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Bankkonten</span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#bank-create">
                <i class="bi bi-plus-lg"></i> Konto hinzufügen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'bank-create' ? 'show' : '' }}" id="bank-create">
            <div class="card-body hairline-top">
                <form method="POST" action="{{ route($prefix.'.bank-accounts.store', $entity) }}">
                    @csrf
                    <input type="hidden" name="_form" value="bank-create">
                    @include('persons.tabs.partials.bank-account-fields', ['account' => null])
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    @if ($entity->bankAccounts->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-bank" message="Keine Bankkonten hinterlegt." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Kontoinhaber</th>
                    <th>IBAN</th>
                    <th>BIC / Bank</th>
                    <th>Währung</th>
                    <th>Status</th>
                    @if ($canEdit)<th class="text-end">Aktionen</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($entity->bankAccounts as $account)
                    <tr>
                        <td>
                            {{ $account->account_holder }}
                            @if ($account->is_primary)
                                <x-status-badge severity="info" icon="bi-star-fill" label="Hauptkonto" class="ms-1" />
                            @endif
                        </td>
                        <td class="font-monospace">{{ trim(chunk_split($account->iban, 4, ' ')) }}</td>
                        <td>
                            {{ $account->bic }}
                            @if ($account->bank_name)<div class="text-muted small">{{ $account->bank_name }}</div>@endif
                        </td>
                        <td>{{ $account->currency }}</td>
                        <td>
                            @if ($account->is_active)
                                <x-status-badge severity="success" icon="bi-check-circle-fill" label="Aktiv" />
                            @else
                                <x-status-badge severity="neutral" icon="bi-dash-circle" label="Inaktiv" />
                            @endif
                        </td>
                        @if ($canEdit)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#bank-edit-{{ $account->id }}" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <x-confirm-form :action="route($prefix.'.bank-accounts.destroy', [$entity, $account])" method="DELETE"
                                                confirm="Dieses Bankkonto wirklich löschen?" icon="bi-trash" label="" />
                            </td>
                        @endif
                    </tr>
                    @if ($canEdit)
                        <tr class="collapse" id="bank-edit-{{ $account->id }}">
                            <td colspan="6">
                                <form method="POST" action="{{ route($prefix.'.bank-accounts.update', [$entity, $account]) }}">
                                    @csrf
                                    @method('PUT')
                                    @include('persons.tabs.partials.bank-account-fields', ['account' => $account])
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
