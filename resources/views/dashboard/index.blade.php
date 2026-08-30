@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Dashboard" label="Übersicht" />

    {{-- Heute relevant (Abschnitt 74) --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Heute relevant</span>
            <span class="text-muted small">{{ format_date(today()) }}</span>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($todayRelevant as $item)
                <li class="list-group-item d-flex align-items-center gap-2 {{ $item['severity'] === 'danger' ? 'fw-semibold' : '' }}">
                    <span aria-hidden="true">{{ $item['icon'] }}</span>
                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" class="text-decoration-none text-body">{{ $item['text'] }}</a>
                    @else
                        <span>{{ $item['text'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    {{-- KPI-Karten (Abschnitt 68) --}}
    <div class="row g-3 mb-4">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-md-4 col-xl-3">
                <x-kpi-card
                    :label="$kpi['label']"
                    :value="$kpi['money'] ? (auth()->user()->privacy_mode ? '•••••• €' : format_money($kpi['value'])) : $kpi['value']"
                    :severity="$kpi['severity']"
                    :hint="$kpi['hint']" />
            </div>
        @endforeach
    </div>

    {{-- Diagramme (Abschnitt 69) --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Darlehensvolumen nach Darlehensgeber</div>
                <div class="card-body">
                    @if (count($charts['volume_by_lender']['labels']) > 0)
                        <canvas id="chartLender" height="220" role="img" aria-label="Balkendiagramm: Darlehensvolumen nach Darlehensgeber"></canvas>
                    @else
                        <x-empty-state icon="bi-bar-chart" message="Noch keine Darlehen vorhanden." />
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Darlehensvolumen nach Darlehensnehmer</div>
                <div class="card-body">
                    @if (count($charts['volume_by_borrower']['labels']) > 0)
                        <canvas id="chartBorrower" height="220" role="img" aria-label="Balkendiagramm: Darlehensvolumen nach Darlehensnehmer"></canvas>
                    @else
                        <x-empty-state icon="bi-bar-chart" message="Noch keine Darlehen vorhanden." />
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">Darlehen nach Status</div>
                <div class="card-body">
                    @if (count($charts['loans_by_status']['labels']) > 0)
                        <canvas id="chartStatus" height="240" role="img" aria-label="Ringdiagramm: Darlehen nach Status"></canvas>
                    @else
                        <x-empty-state icon="bi-pie-chart" message="Noch keine Darlehen vorhanden." />
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header">Soll-Cashflows der nächsten 12 Monate</div>
                <div class="card-body">
                    <canvas id="chartCashflow" height="240" role="img" aria-label="Liniendiagramm: Soll-Cashflows der nächsten 12 Monate"></canvas>
                    <div class="text-muted small mt-2">Sollwerte aus dem Zahlungsplan. Systemseitig angenommene Erfüllung ist keine bestätigte Zahlung.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Administrator-Zusatzblock (Abschnitt 136) --}}
    @if ($adminOverview)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Systemstatus (Administration)</span>
                @if (Route::has('admin.status'))
                    <a href="{{ route('admin.status') }}" class="small">Details</a>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <x-kpi-card label="Offene Einladungen" :value="(string) $adminOverview['open_invitations']" />
                    </div>
                    <div class="col-6 col-md-3">
                        <x-kpi-card label="Fehlgeschlagene Logins (24 h)"
                                    :value="(string) $adminOverview['failed_logins_24h']"
                                    :severity="$adminOverview['failed_logins_24h'] > 0 ? 'warning' : 'success'" />
                    </div>
                    <div class="col-6 col-md-3">
                        <x-kpi-card label="Offene Hintergrundjobs" :value="(string) $adminOverview['open_jobs']"
                                    :hint="'Fehlgeschlagen: '.$adminOverview['failed_jobs']"
                                    :severity="$adminOverview['failed_jobs'] > 0 ? 'danger' : null" />
                    </div>
                    <div class="col-6 col-md-3">
                        @php($lastBackup = $adminOverview['last_backup'])
                        <x-kpi-card label="Letztes Backup"
                                    :value="$lastBackup ? ($lastBackup['success'] ? 'OK' : 'Fehler') : 'noch keins'"
                                    :severity="$lastBackup ? ($lastBackup['success'] ? 'success' : 'danger') : 'warning'"
                                    :hint="$lastBackup['finished_at'] ?? null" />
                    </div>
                </div>
                @if ($adminOverview['recalculation_errors']->isNotEmpty())
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Letzte Neuberechnungsfehler:</strong>
                        <ul class="mb-0">
                            @foreach ($adminOverview['recalculation_errors'] as $error)
                                <li>{{ format_datetime($error->created_at) }}: {{ \Illuminate\Support\Str::limit($error->error, 140) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script>
        (function () {
            const gold = '#E3AC48', anthracite = '#2E2D2E', gray = '#9F9F9F',
                info = '#1D5FA6', success = '#1E7B34', warning = '#B77400', danger = '#B3261E';
            const euro = (value) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
            const moneyTicks = { ticks: { callback: (v) => euro(v) } };

            const lender = document.getElementById('chartLender');
            if (lender) {
                new Chart(lender, {
                    type: 'bar',
                    data: {
                        labels: @json($charts['volume_by_lender']['labels']),
                        datasets: [{ label: 'Volumen', data: @json($charts['volume_by_lender']['values']), backgroundColor: gold }]
                    },
                    options: {
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => euro(c.parsed.y) } } },
                        scales: { y: moneyTicks }
                    }
                });
            }

            const borrower = document.getElementById('chartBorrower');
            if (borrower) {
                new Chart(borrower, {
                    type: 'bar',
                    data: {
                        labels: @json($charts['volume_by_borrower']['labels']),
                        datasets: [{ label: 'Volumen', data: @json($charts['volume_by_borrower']['values']), backgroundColor: anthracite }]
                    },
                    options: {
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => euro(c.parsed.y) } } },
                        scales: { y: moneyTicks }
                    }
                });
            }

            const status = document.getElementById('chartStatus');
            if (status) {
                new Chart(status, {
                    type: 'doughnut',
                    data: {
                        labels: @json($charts['loans_by_status']['labels']),
                        datasets: [{ data: @json($charts['loans_by_status']['values']),
                            backgroundColor: [gold, info, success, warning, danger, gray, anthracite, '#7A6A45', '#4E6E8E', '#8E4E4E'] }]
                    },
                    options: { plugins: { legend: { position: 'bottom' } } }
                });
            }

            const cashflow = document.getElementById('chartCashflow');
            if (cashflow) {
                new Chart(cashflow, {
                    type: 'line',
                    data: {
                        labels: @json($charts['cashflow_12m']['labels']),
                        datasets: [
                            { label: 'Zinsen (Soll)', data: @json($charts['cashflow_12m']['interest']), borderColor: gold, backgroundColor: gold, tension: 0.2 },
                            { label: 'Tilgungen (Soll)', data: @json($charts['cashflow_12m']['principal']), borderColor: info, backgroundColor: info, tension: 0.2 }
                        ]
                    },
                    options: {
                        plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + euro(c.parsed.y) } } },
                        scales: { y: moneyTicks }
                    }
                });
            }
        })();
    </script>
@endpush
