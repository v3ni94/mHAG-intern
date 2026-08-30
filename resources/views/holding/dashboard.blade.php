@extends('layouts.app')
@section('title', 'Holding-Dashboard')
@section('content')
    <x-page-header title="Holding-Dashboard" label="Müller Holding AG">
        @can('shares.list')
            <form method="POST" action="{{ route('shareholders.list.create') }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> Aktionärsliste erzeugen
                </button>
            </form>
        @endcan
    </x-page-header>

    {{-- KPI-Karten (Abschnitt 106) --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card label="Grundkapital" :value="format_money($kpis['base_capital'])" icon="bi-bank" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card label="Aktien" :value="number_format($kpis['total_shares'], 0, ',', '.')" icon="bi-collection" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card label="Aktionäre" :value="$kpis['shareholder_count']" icon="bi-people" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card label="Beteiligungen" :value="$kpis['investment_count']" icon="bi-pie-chart" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card label="Offene Beschlüsse" :value="$kpis['open_resolutions']" :severity="$kpis['open_resolutions'] > 0 ? 'warning' : null" icon="bi-journal-check" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card label="Offene Signaturen" :value="$kpis['open_signatures']" :severity="$kpis['open_signatures'] > 0 ? 'warning' : null" icon="bi-pen" />
        </div>
    </div>

    {{-- Darlehens-KPIs (Daten der Darlehens-Engine, MHAG als Darlehensgeberin) --}}
    <div class="row g-3 mb-4">
        @if ($loanKpis !== null)
            <div class="col-6 col-md-3">
                <x-kpi-card label="Darlehen als Geberin" :value="$loanKpis['loan_count']" icon="bi-cash-stack" />
            </div>
            <div class="col-6 col-md-3">
                <x-kpi-card label="Aktuelle Forderungen" :value="format_money($loanKpis['total_receivable'])" icon="bi-cash-coin" />
            </div>
            <div class="col-6 col-md-3">
                <x-kpi-card label="Offene Zinsen" :value="format_money($loanKpis['interest_open'])" icon="bi-percent" />
            </div>
            <div class="col-6 col-md-3">
                <x-kpi-card label="Überfällig" :value="format_money($loanKpis['overdue_amount'])" :severity="\App\Support\Money::isPositive($loanKpis['overdue_amount']) ? 'danger' : 'success'" icon="bi-exclamation-triangle" />
            </div>
        @else
            <div class="col-12">
                <div class="alert alert-secondary mb-0">
                    Die Darlehenskennzahlen stehen zur Verfügung, sobald das Darlehensmodul aktiviert ist.
                </div>
            </div>
        @endif
    </div>

    <div class="row g-3">
        {{-- Aktionärsstruktur (Donut, Chart.js lokal) --}}
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Aktionärsstruktur</span>
                    <a href="{{ route('shareholders.index') }}" class="small">Alle Aktionäre</a>
                </div>
                <div class="card-body">
                    @if (count($chart['values']) > 0)
                        <canvas id="shareholderChart" height="220" role="img" aria-label="Aktionärsstruktur als Ringdiagramm"></canvas>
                        <table class="table table-sm mt-3 mb-0">
                            <thead><tr><th>Aktionär</th><th class="text-end">Aktien</th><th class="text-end">Anteil</th></tr></thead>
                            <tbody>
                            @foreach ($holdings as $row)
                                <tr>
                                    <td>{{ $row['shareholder']->entity?->display_name }}</td>
                                    <td class="text-end">{{ number_format($row['shares'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ format_percent($row['percentage']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <x-empty-state icon="bi-pie-chart" message="Keine wirksamen Aktienbestände vorhanden." />
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            {{-- Letzte Aktienbewegungen --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Letzte Aktienbewegungen</span>
                    <a href="{{ route('share-transactions.index') }}" class="small">Zum Register</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Nr.</th><th>Art</th><th>Von/An</th><th class="text-end">Stück</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($recentTransactions as $t)
                                <tr>
                                    <td><a href="{{ route('share-transactions.show', $t) }}">{{ $t->transaction_number }}</a></td>
                                    <td>{{ $t->type?->label() }}</td>
                                    <td class="small">
                                        {{ $t->seller?->entity?->display_name ?? 'Gesellschaft' }}
                                        <i class="bi bi-arrow-right"></i>
                                        {{ $t->buyer?->entity?->display_name ?? 'Gesellschaft' }}
                                    </td>
                                    <td class="text-end">{{ number_format($t->share_count, 0, ',', '.') }}</td>
                                    <td><x-enum-badge :enum="$t->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Keine Aktienbewegungen vorhanden.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Letzte Beschlüsse --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Letzte Beschlüsse</span>
                    <a href="{{ route('resolutions.index') }}" class="small">Zum Register</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Nr.</th><th>Titel</th><th>Art</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($recentResolutions as $r)
                                <tr>
                                    <td><a href="{{ route('resolutions.show', $r) }}">{{ $r->resolution_number }}</a></td>
                                    <td>{{ \Illuminate\Support\Str::limit($r->title, 45) }}</td>
                                    <td class="small">{{ $r->type?->label() }}</td>
                                    <td><x-enum-badge :enum="$r->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">Keine Beschlüsse vorhanden.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Offene Signaturen --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Offene Signaturen</span>
                    <a href="{{ route('signatures.index') }}" class="small">Alle Anfragen</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Anfrage</th><th>Unterzeichner</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($openSignatureRequests as $sr)
                                <tr>
                                    <td><a href="{{ route('signatures.show', $sr) }}">Anfrage #{{ $sr->id }}</a></td>
                                    <td class="small">
                                        @foreach ($sr->participants as $p)
                                            <span class="me-1">{{ $p->entity?->display_name }}</span>
                                        @endforeach
                                    </td>
                                    <td><x-enum-badge :enum="$sr->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Keine offenen Signaturanfragen.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if (count($chart['values']) > 0)
        <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
        <script>
            new Chart(document.getElementById('shareholderChart'), {
                type: 'doughnut',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [{
                        data: @json($chart['values']),
                        backgroundColor: ['#E3AC48', '#2E2D2E', '#9F9F9F', '#B77400', '#1D5FA6', '#1E7B34', '#B3261E', '#FBF6EC'],
                        borderColor: '#FFFFFF',
                        borderWidth: 2,
                    }],
                },
                options: {
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '60%',
                },
            });
        </script>
    @endif
@endpush
