@extends('layouts.app')

@section('title', 'Darlehen')

@section('content')
    <x-page-header title="Darlehen" label="Finanzen">
        @can('loans.create')
            <a href="{{ route('loans.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Neues Darlehen
            </a>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('loans.index') }}" class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-q">Suche (Nummer oder Bezeichnung)</label>
                    <input type="search" id="filter-q" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="z. B. DAR-2026-00001">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-lender">Darlehensgeber</label>
                    <select id="filter-lender" name="lender_entity_id" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($entities as $entity)
                            <option value="{{ $entity->id }}" @selected((string) $filters['lender_entity_id'] === (string) $entity->id)>{{ $entity->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-borrower">Darlehensnehmer</label>
                    <select id="filter-borrower" name="borrower_entity_id" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($entities as $entity)
                            <option value="{{ $entity->id }}" @selected((string) $filters['borrower_entity_id'] === (string) $entity->id)>{{ $entity->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filtern</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nummer</th>
                        <th>Bezeichnung</th>
                        <th>Darlehensgeber</th>
                        <th>Darlehensnehmer</th>
                        <th class="text-end">Darlehenssumme</th>
                        <th>Fälligkeit</th>
                        <th>Status</th>
                        @if ($isInternal)
                            <th>Risiko</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loans as $loan)
                        <tr>
                            <td><a href="{{ route('loans.show', $loan) }}" class="fw-semibold">{{ $loan->loan_number }}</a></td>
                            <td>{{ $loan->title }}</td>
                            <td>{{ $loan->lender?->display_name }}</td>
                            <td>{{ $loan->borrower?->display_name }}</td>
                            <td class="text-end"><x-money :amount="$loan->principal_amount" :currency="$loan->currency" /></td>
                            <td>{{ $loan->due_date ? format_date($loan->due_date) : ($loan->contract_end ? format_date($loan->contract_end) : 'unbefristet') }}</td>
                            <td><x-enum-badge :enum="$loan->status" /></td>
                            @if ($isInternal)
                                <td>
                                    @if ($loan->risk_rating)
                                        <x-enum-badge :enum="$loan->risk_rating" />
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isInternal ? 8 : 7 }}">
                                <x-empty-state icon="bi-cash-stack" message="Keine Darlehen gefunden." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($loans->hasPages())
            <div class="card-footer">{{ $loans->links() }}</div>
        @endif
    </div>
@endsection
