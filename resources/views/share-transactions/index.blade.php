@extends('layouts.app')
@section('title', 'Aktienbewegungen')
@section('content')
    <x-page-header title="Aktienbewegungen" label="Register">
        @can('shares.prepare')
            <a href="{{ route('share-transactions.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Bewegung erfassen
            </a>
        @endcan
    </x-page-header>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('share-transactions.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="type">Transaktionsart</label>
                    <select name="type" id="type" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="status">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="year">Jahr</label>
                    <select name="year" id="year" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary btn-sm">Filtern</button>
                    <a href="{{ route('share-transactions.index') }}" class="btn btn-link btn-sm">Zurücksetzen</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Art</th>
                        <th>Abgebend</th>
                        <th>Empfangend</th>
                        <th class="text-end">Stück</th>
                        <th class="text-end">Gesamtpreis</th>
                        <th>Wirtschaftlicher Übergang</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($transactions as $t)
                        <tr>
                            <td><a href="{{ route('share-transactions.show', $t) }}">{{ $t->transaction_number }}</a></td>
                            <td>{{ $t->type?->label() }}</td>
                            <td>{{ $t->seller?->entity?->display_name ?? 'Gesellschaft' }}</td>
                            <td>{{ $t->buyer?->entity?->display_name ?? 'Gesellschaft' }}</td>
                            <td class="text-end">{{ number_format($t->share_count, 0, ',', '.') }}</td>
                            <td class="text-end">@if ($t->total_price !== null)<x-money :amount="$t->total_price" />@endif</td>
                            <td>{{ format_date($t->economic_transfer_date) }}</td>
                            <td><x-enum-badge :enum="$t->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-arrow-repeat" message="Keine Aktienbewegungen gefunden." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($transactions->hasPages())
            <div class="card-footer">{{ $transactions->links() }}</div>
        @endif
    </div>
@endsection
