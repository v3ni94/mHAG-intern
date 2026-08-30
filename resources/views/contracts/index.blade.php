@extends('layouts.app')

@section('title', 'Verträge')

@section('content')
    <x-page-header title="Verträge" label="Vertragsverwaltung">
        <a href="{{ route('contract-templates.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-ruled"></i> Vorlagen
        </a>
        @can('contracts.create')
            <a href="{{ route('contracts.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Vertrag erstellen
            </a>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('contracts.index') }}" class="mb-3 d-flex gap-2 flex-wrap">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 320px;"
               placeholder="Vertragsnummer oder Titel ...">
        <select name="status" class="form-select form-select-sm" style="max-width: 200px;">
            <option value="">Alle Status</option>
            <option value="draft" @selected(request('status') === 'draft')>Entwurf</option>
            <option value="final" @selected(request('status') === 'final')>Final</option>
            <option value="signed" @selected(request('status') === 'signed')>Unterschrieben</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Storniert</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filtern</button>
    </form>

    <div class="card">
        <div class="card-body p-0">
            @if ($contracts->isEmpty())
                <x-empty-state icon="bi-file-earmark-text" message="Keine Verträge gefunden." />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Vertragsnummer</th>
                            <th>Titel</th>
                            <th>Darlehen</th>
                            <th>Vorlage</th>
                            <th>Status</th>
                            <th>Finalisiert am</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($contracts as $contract)
                            <tr>
                                <td>
                                    <a href="{{ route('contracts.show', $contract) }}" class="fw-bold text-decoration-none">
                                        {{ $contract->contract_number }}
                                    </a>
                                </td>
                                <td>{{ $contract->title }}</td>
                                <td>{{ $contract->loan?->loan_number ?: '–' }}</td>
                                <td>
                                    @if ($contract->templateVersion)
                                        {{ $contract->templateVersion->template?->name }} (v{{ $contract->templateVersion->version }})
                                    @else
                                        –
                                    @endif
                                </td>
                                <td>
                                    @switch($contract->status)
                                        @case('draft')<x-status-badge severity="warning" icon="bi-pencil" label="ENTWURF" />@break
                                        @case('final')<x-status-badge severity="info" label="Final" />@break
                                        @case('signed')<x-status-badge severity="success" label="Unterschrieben" />@break
                                        @case('cancelled')<x-status-badge severity="danger" label="Storniert" />@break
                                        @default<x-status-badge severity="neutral" :label="$contract->status" />
                                    @endswitch
                                </td>
                                <td>{{ $contract->finalized_at ? format_datetime($contract->finalized_at) : '–' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('contracts.pdf', $contract) }}" class="btn btn-sm btn-outline-secondary" title="PDF">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-3">{{ $contracts->links() }}</div>
@endsection
