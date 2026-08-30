@extends('layouts.app')

@section('title', 'Sicherheiten')

@section('content')
    <x-page-header title="Sicherheiten und Bürgschaften" label="Finanzen">
        <span class="small text-muted">Anlage und Bearbeitung erfolgen auf der jeweiligen Darlehens-Detailseite (Reiter Sicherheiten).</span>
    </x-page-header>

    @php($today = now()->toDateString())
    @php($warningDate = now()->addDays($warningDays)->toDateString())

    <form method="GET" action="{{ route('securities.index') }}" class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-type">Art</label>
                    <select id="filter-type" name="type" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        <option value="active" @selected($filters['status'] === 'active')>Aktiv</option>
                        <option value="released" @selected($filters['status'] === 'released')>Freigegeben</option>
                        <option value="expired" @selected($filters['status'] === 'expired')>Abgelaufen</option>
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
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filtern</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card mb-3">
        <div class="card-header">Sicherheiten</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Darlehen</th>
                        <th>Art</th>
                        <th>Sicherungsgeber</th>
                        <th class="text-end">Nominalwert</th>
                        <th class="text-end">Interner Wert</th>
                        <th>Rang</th>
                        <th>Ende</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($securities as $security)
                        <tr>
                            <td>
                                <a href="{{ route('loans.show', ['loan' => $security->loan_id, 'tab' => 'sicherheiten']) }}">{{ $security->loan?->loan_number }}</a>
                            </td>
                            <td>{{ $security->type?->label() }}</td>
                            <td>{{ $security->provider?->display_name }}</td>
                            <td class="text-end">@if ($security->nominal_value !== null)<x-money :amount="$security->nominal_value" />@endif</td>
                            <td class="text-end">@if ($security->internal_value !== null)<x-money :amount="$security->internal_value" />@endif</td>
                            <td>{{ $security->rank }}</td>
                            <td>
                                {{ $security->valid_until ? format_date($security->valid_until) : 'unbefristet' }}
                                @if ($security->status === 'active' && $security->valid_until)
                                    @if ($security->valid_until->toDateString() < $today)
                                        <x-status-badge severity="danger" label="Abgelaufen" />
                                    @elseif ($security->valid_until->toDateString() <= $warningDate)
                                        <x-status-badge severity="warning" :label="'Läuft ab am '.format_date($security->valid_until)" />
                                    @endif
                                @endif
                            </td>
                            <td>
                                @switch($security->status)
                                    @case('released') <x-status-badge severity="neutral" label="Freigegeben" /> @break
                                    @case('expired') <x-status-badge severity="danger" label="Abgelaufen" /> @break
                                    @default <x-status-badge severity="success" label="Aktiv" />
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-shield-check" message="Keine Sicherheiten gefunden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($securities->hasPages())
            <div class="card-footer">{{ $securities->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">Bürgschaften</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Darlehen</th>
                        <th>Bürge</th>
                        <th>Bürgschaftsart</th>
                        <th class="text-end">Höchstbetrag</th>
                        <th>Ende</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guarantees as $guarantee)
                        <tr>
                            <td>
                                <a href="{{ route('loans.show', ['loan' => $guarantee->loan_id, 'tab' => 'sicherheiten']) }}">{{ $guarantee->loan?->loan_number }}</a>
                            </td>
                            <td>{{ $guarantee->guarantor?->display_name }}</td>
                            <td>{{ $guarantee->guarantee_type }}</td>
                            <td class="text-end">@if ($guarantee->max_amount !== null)<x-money :amount="$guarantee->max_amount" />@endif</td>
                            <td>
                                {{ $guarantee->valid_until ? format_date($guarantee->valid_until) : 'unbefristet' }}
                                @if ($guarantee->status === 'active' && $guarantee->valid_until)
                                    @if ($guarantee->valid_until->toDateString() < $today)
                                        <x-status-badge severity="danger" label="Abgelaufen" />
                                    @elseif ($guarantee->valid_until->toDateString() <= $warningDate)
                                        <x-status-badge severity="warning" :label="'Läuft ab am '.format_date($guarantee->valid_until)" />
                                    @endif
                                @endif
                            </td>
                            <td>
                                @switch($guarantee->status)
                                    @case('released') <x-status-badge severity="neutral" label="Freigegeben" /> @break
                                    @case('expired') <x-status-badge severity="danger" label="Abgelaufen" /> @break
                                    @default <x-status-badge severity="success" label="Aktiv" />
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-person-check" message="Keine Bürgschaften gefunden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($guarantees->hasPages())
            <div class="card-footer">{{ $guarantees->links() }}</div>
        @endif
    </div>
@endsection
