@extends('layouts.app')
@section('title', 'Beteiligungen')
@section('content')
    <x-page-header title="Beteiligungen" label="Müller Holding AG">
        @can('shares.prepare')
            <a href="{{ route('investments.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Beteiligung anlegen
            </a>
        @endcan
    </x-page-header>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('investments.index') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label col-form-label-sm" for="status">Status</label>
                </div>
                <div class="col-auto">
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktiv</option>
                        <option value="sold" @selected(($filters['status'] ?? '') === 'sold')>Verkauft</option>
                        <option value="liquidated" @selected(($filters['status'] ?? '') === 'liquidated')>Liquidiert</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm">Filtern</button>
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
                        <th>Unternehmen</th>
                        <th class="text-end">Quote</th>
                        <th class="text-end">Anteile</th>
                        <th>Anschaffung</th>
                        <th class="text-end">Anschaffungskosten</th>
                        <th class="text-end">Interner Wert</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($investments as $investment)
                        <tr>
                            <td><a href="{{ route('investments.show', $investment) }}">{{ $investment->company?->display_name }}</a></td>
                            <td class="text-end">{{ $investment->share_percentage !== null ? format_percent($investment->share_percentage) : '' }}</td>
                            <td class="text-end">{{ $investment->share_count !== null ? number_format($investment->share_count, 0, ',', '.') : '' }}</td>
                            <td>{{ format_date($investment->acquired_on) }}</td>
                            <td class="text-end">@if ($investment->acquisition_cost !== null)<x-money :amount="$investment->acquisition_cost" />@endif</td>
                            <td class="text-end">
                                @if ($investment->current_value !== null)
                                    <x-money :amount="$investment->current_value" />
                                @else
                                    <span class="text-muted small">nicht bewertet</span>
                                @endif
                            </td>
                            <td>
                                @if ($investment->status === 'active')
                                    <x-status-badge severity="success" label="Aktiv" />
                                @elseif ($investment->status === 'sold')
                                    <x-status-badge severity="neutral" label="Verkauft" />
                                @else
                                    <x-status-badge severity="neutral" label="Liquidiert" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-pie-chart" message="Keine Beteiligungen vorhanden." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($investments->hasPages())
            <div class="card-footer">{{ $investments->links() }}</div>
        @endif
    </div>
@endsection
