@extends('layouts.app')

@section('title', 'Fälligkeiten')

@section('content')
    <x-page-header title="Fälligkeiten" label="Finanzen">
        <span class="small text-muted">Zahlungsplan-Positionen aller sichtbaren Darlehen</span>
    </x-page-header>

    <form method="GET" action="{{ route('due-items.index') }}" class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-type">Art</label>
                    <select id="filter-type" name="item_type" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($itemTypes as $type)
                            <option value="{{ $type->value }}" @selected($filters['item_type'] === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-loan">Darlehen</label>
                    <select id="filter-loan" name="loan_id" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}" @selected((string) $filters['loan_id'] === (string) $loan->id)>{{ $loan->loan_number }} {{ $loan->title ? '· '.$loan->title : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-from">Überfällig ab</label>
                    <input type="date" id="filter-from" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-to">Kommend bis</label>
                    <input type="date" id="filter-to" name="to" value="{{ $filters['to'] ?: $horizon }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filtern</button>
                </div>
            </div>
        </div>
    </form>

    {{-- Überfällig (rot) --}}
    <div class="card mb-3 border-danger">
        <div class="card-header text-danger">
            <i class="bi bi-exclamation-octagon-fill"></i> Überfällig ({{ $overdue->count() }})
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fälligkeit</th>
                        <th>Darlehen</th>
                        <th>Art</th>
                        <th class="text-end">SOLL</th>
                        <th class="text-end">Offen</th>
                        <th>Status</th>
                        <th>Herkunft</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($overdue as $item)
                        <tr>
                            <td class="text-danger fw-semibold">{{ format_date($item->due_date) }}</td>
                            <td><a href="{{ route('loans.show', ['loan' => $item->loan_id, 'tab' => 'zahlungsplan']) }}">{{ $item->loan?->loan_number }}</a></td>
                            <td>{{ $item->item_type?->label() }}</td>
                            <td class="text-end"><x-money :amount="$item->planned_amount" /></td>
                            <td class="text-end fw-semibold"><x-money :amount="$item->openAmount()" /></td>
                            <td><x-enum-badge :enum="$item->status" /></td>
                            <td><x-origin-badge :origin="$item->origin" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-check-circle" message="Keine überfälligen Positionen." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Heute fällig (orange) --}}
    <div class="card mb-3 border-warning">
        <div class="card-header" style="color: #B77400;">
            <i class="bi bi-exclamation-triangle-fill"></i> Heute fällig ({{ $dueToday->count() }})
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fälligkeit</th>
                        <th>Darlehen</th>
                        <th>Art</th>
                        <th class="text-end">SOLL</th>
                        <th class="text-end">Offen</th>
                        <th>Status</th>
                        <th>Herkunft</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dueToday as $item)
                        <tr>
                            <td class="fw-semibold">{{ format_date($item->due_date) }}</td>
                            <td><a href="{{ route('loans.show', ['loan' => $item->loan_id, 'tab' => 'zahlungsplan']) }}">{{ $item->loan?->loan_number }}</a></td>
                            <td>{{ $item->item_type?->label() }}</td>
                            <td class="text-end"><x-money :amount="$item->planned_amount" /></td>
                            <td class="text-end"><x-money :amount="$item->openAmount()" /></td>
                            <td><x-enum-badge :enum="$item->status" /></td>
                            <td><x-origin-badge :origin="$item->origin" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-calendar-check" message="Heute sind keine Positionen fällig." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Kommend --}}
    <div class="card">
        <div class="card-header">
            <i class="bi bi-calendar3"></i> Kommend (bis {{ format_date($horizon) }})
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fälligkeit</th>
                        <th>Darlehen</th>
                        <th>Art</th>
                        <th class="text-end">SOLL</th>
                        <th>Status</th>
                        <th>Herkunft</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($upcoming as $item)
                        <tr>
                            <td>{{ format_date($item->due_date) }}</td>
                            <td><a href="{{ route('loans.show', ['loan' => $item->loan_id, 'tab' => 'zahlungsplan']) }}">{{ $item->loan?->loan_number }}</a></td>
                            <td>{{ $item->item_type?->label() }}</td>
                            <td class="text-end"><x-money :amount="$item->planned_amount" /></td>
                            <td><x-enum-badge :enum="$item->status" /></td>
                            <td><x-origin-badge :origin="$item->origin" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-calendar3" message="Keine kommenden Positionen im gewählten Zeitraum." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($upcoming->hasPages())
            <div class="card-footer">{{ $upcoming->links() }}</div>
        @endif
    </div>
@endsection
