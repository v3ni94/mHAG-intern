@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" label="Report">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Alle Reports</a>
        @foreach (['pdf' => 'PDF', 'xlsx' => 'XLSX', 'csv' => 'CSV'] as $format => $label)
            <a href="{{ route('reports.show', array_merge(['key' => $key, 'format' => $format], request()->except('format', 'page'))) }}"
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download"></i> {{ $label }}
            </a>
        @endforeach
    </x-page-header>

    <p class="text-muted">{{ $description }}</p>

    {{-- Filter (werden in den Export übernommen) --}}
    <form method="GET" class="row g-2 mb-3">
        @switch($key)
            @case('darlehensbestand')
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" aria-label="Status">
                        <option value="">Alle Status</option>
                        @foreach (\App\Enums\LoanStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                           placeholder="Nummer oder Titel" aria-label="Suche">
                </div>
                @break
            @case('offene-posten')
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0" for="f-bis">Offen bis einschließlich</label>
                    <input type="date" id="f-bis" name="bis" value="{{ $report['filters']['bis'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0" for="f-typ">Art</label>
                    <select id="f-typ" name="typ" class="form-select form-select-sm">
                        <option value="">Alle Arten</option>
                        @foreach (\App\Enums\RepaymentItemType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('typ') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @break
            @case('zinsen-soll-ist')
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0" for="f-jahr">Jahr</label>
                    <input type="number" id="f-jahr" name="jahr" min="1990" max="2100" value="{{ $report['filters']['jahr'] }}" class="form-control form-control-sm">
                </div>
                @break
            @case('faelligkeiten')
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0" for="f-von">Von</label>
                    <input type="date" id="f-von" name="von" value="{{ $report['filters']['von'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0" for="f-bis">Bis</label>
                    <input type="date" id="f-bis" name="bis" value="{{ $report['filters']['bis'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0" for="f-typ">Art</label>
                    <select id="f-typ" name="typ" class="form-select form-select-sm">
                        <option value="">Alle Arten</option>
                        @foreach (\App\Enums\RepaymentItemType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('typ') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @break
            @case('sicherheiten')
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" aria-label="Status">
                        <option value="">Alle Status</option>
                        <option value="active" @selected(request('status') === 'active')>Aktiv</option>
                        <option value="released" @selected(request('status') === 'released')>Freigegeben</option>
                        <option value="expired" @selected(request('status') === 'expired')>Abgelaufen</option>
                    </select>
                </div>
                @break
            @case('aktionaersliste')
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0" for="f-stichtag">Stichtag</label>
                    <input type="date" id="f-stichtag" name="stichtag" value="{{ $report['filters']['stichtag'] ?? '' }}" class="form-control form-control-sm">
                </div>
                @break
            @case('aktienbewegungen')
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0" for="f-von">Von</label>
                    <input type="date" id="f-von" name="von" value="{{ $report['filters']['von'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0" for="f-bis">Bis</label>
                    <input type="date" id="f-bis" name="bis" value="{{ $report['filters']['bis'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0" for="f-status">Status</label>
                    <select id="f-status" name="status" class="form-select form-select-sm">
                        <option value="">Alle Status</option>
                        @foreach (\App\Enums\ShareTransactionStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @break
            @case('beteiligungen')
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" aria-label="Status">
                        <option value="">Alle Status</option>
                        <option value="active" @selected(request('status') === 'active')>Aktiv</option>
                        <option value="sold" @selected(request('status') === 'sold')>Veräußert</option>
                        <option value="liquidated" @selected(request('status') === 'liquidated')>Liquidiert</option>
                    </select>
                </div>
                @break
            @case('beschlussregister')
                <div class="col-6 col-md-2">
                    <input type="number" name="jahr" min="1990" max="2100" value="{{ request('jahr') }}" class="form-control form-control-sm"
                           placeholder="Jahr" aria-label="Jahr">
                </div>
                <div class="col-6 col-md-3">
                    <select name="typ" class="form-select form-select-sm" aria-label="Art">
                        <option value="">Alle Arten</option>
                        @foreach (\App\Enums\ResolutionType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('typ') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" aria-label="Status">
                        <option value="">Alle Status</option>
                        @foreach (\App\Enums\ResolutionStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @break
            @case('organhistorie')
                <div class="col-6 col-md-3">
                    <select name="gremium" class="form-select form-select-sm" aria-label="Gremium">
                        <option value="">Alle Gremien</option>
                        <option value="board" @selected(request('gremium') === 'board')>Vorstand</option>
                        <option value="supervisory_board" @selected(request('gremium') === 'supervisory_board')>Aufsichtsrat</option>
                        <option value="advisory_board" @selected(request('gremium') === 'advisory_board')>Beirat</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex align-items-center">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="f-aktive" name="nur_aktive" value="1" @checked(request('nur_aktive'))>
                        <label class="form-check-label small" for="f-aktive">Nur aktive Mandate</label>
                    </div>
                </div>
                @break
        @endswitch

        @if (! in_array($key, ['ueberfaellige-darlehen', 'darlehen-je-kreditgeber', 'darlehen-je-kreditnehmer'], true))
            <div class="col-6 col-md-2 d-flex align-items-end">
                <button class="btn btn-sm btn-outline-secondary">Filtern</button>
            </div>
        @endif
    </form>

    @if ($report['hint'])
        <div class="alert alert-info py-2 small">{{ $report['hint'] }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        @foreach ($report['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['columns']) }}">
                                <x-empty-state icon="bi-clipboard-data" message="Keine Daten für die gewählten Filter." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-2">{{ count($report['rows']) }} Zeile(n).</p>
@endsection
