@extends('layouts.app')

@section('title', 'Liquiditätsplanung')

@section('content')
    <x-page-header title="Liquiditätsplanung" label="Finanzen">
        <span class="small text-muted">Zeitraum: {{ format_date($from) }} bis {{ format_date($to) }}</span>
    </x-page-header>

    <form method="GET" action="{{ route('liquidity.index') }}" class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="preset">Zeitraum</label>
                    <select id="preset" name="preset" class="form-select form-select-sm">
                        <option value="month" @selected($preset === 'month')>Aktueller Monat</option>
                        <option value="quarter" @selected($preset === 'quarter')>Aktuelles Quartal</option>
                        <option value="year" @selected($preset === 'year')>Aktuelles Jahr</option>
                        <option value="next12" @selected($preset === 'next12')>Nächste 12 Monate</option>
                        <option value="custom" @selected($preset === 'custom')>Frei wählbar</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="from">Von (bei freier Wahl)</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="to">Bis (bei freier Wahl)</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Anzeigen</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <x-kpi-card label="Erwartete Zinsen" :value="format_money($totals['interest'])" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Erwartete Tilgungen" :value="format_money($totals['principal'])" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Erwartete Gebühren" :value="format_money($totals['fee'])" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Geplante Auszahlungen" :value="format_money($totals['disbursements'])"
                        :severity="\App\Support\Money::isPositive($totals['disbursements']) ? 'warning' : null" />
        </div>
    </div>

    @unless ($privacyMode)
        <div class="card mb-3">
            <div class="card-header">Monatliche Cashflows (erwartet)</div>
            <div class="card-body">
                <canvas id="liquidityChart" height="90" role="img"
                        aria-label="Balkendiagramm der erwarteten monatlichen Zinsen, Tilgungen, Gebühren und geplanten Auszahlungen"></canvas>
            </div>
        </div>
    @endunless

    <div class="card">
        <div class="card-header">Monatsübersicht</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Monat</th>
                        <th class="text-end">Zinsen</th>
                        <th class="text-end">Tilgungen</th>
                        <th class="text-end">Gebühren</th>
                        <th class="text-end">Auszahlungen</th>
                        <th class="text-end">Netto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($months as $month)
                        <tr>
                            <td class="fw-semibold">{{ $month['label'] }}</td>
                            <td class="text-end"><x-money :amount="$month['interest']" /></td>
                            <td class="text-end"><x-money :amount="$month['principal']" /></td>
                            <td class="text-end"><x-money :amount="$month['fee']" /></td>
                            <td class="text-end {{ \App\Support\Money::isPositive($month['disbursements']) ? 'text-danger' : '' }}"><x-money :amount="$month['disbursements']" /></td>
                            <td class="text-end fw-semibold {{ \App\Support\Money::isNegative($month['net']) ? 'text-danger' : '' }}">
                                <x-money :amount="$month['net']" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-graph-up" message="Keine Daten im gewählten Zeitraum." /></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="table-light fw-semibold">
                        <td>Summe</td>
                        <td class="text-end"><x-money :amount="$totals['interest']" /></td>
                        <td class="text-end"><x-money :amount="$totals['principal']" /></td>
                        <td class="text-end"><x-money :amount="$totals['fee']" /></td>
                        <td class="text-end"><x-money :amount="$totals['disbursements']" /></td>
                        <td class="text-end"><x-money :amount="$totals['net']" /></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Grundlage: offene Zahlungsplan-Positionen (SOLL abzüglich erfasster IST-Werte) und geplante Auszahlungen der sichtbaren Darlehen.
            Systemseitig angenommene Zahlungen gelten als erfüllt und erscheinen hier nicht.
        </div>
    </div>
@endsection

@unless ($privacyMode)
    @push('scripts')
        <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
        <script>
            (function () {
                const el = document.getElementById('liquidityChart');
                if (!el || typeof Chart === 'undefined') {
                    return;
                }
                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: @json($chart['labels']),
                        datasets: [
                            {
                                label: 'Zinsen',
                                data: @json($chart['interest']),
                                backgroundColor: '#E3AC48',
                                stack: 'cashflow'
                            },
                            {
                                label: 'Tilgungen',
                                data: @json($chart['principal']),
                                backgroundColor: '#2E2D2E',
                                stack: 'cashflow'
                            },
                            {
                                label: 'Gebühren',
                                data: @json($chart['fee']),
                                backgroundColor: '#9F9F9F',
                                stack: 'cashflow'
                            },
                            {
                                label: 'Auszahlungen',
                                data: @json($chart['disbursements']),
                                backgroundColor: '#B3261E',
                                stack: 'cashflow'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: { stacked: true },
                            y: {
                                stacked: true,
                                ticks: {
                                    callback: function (value) {
                                        return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value);
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(context.parsed.y);
                                    }
                                }
                            }
                        }
                    }
                });
            })();
        </script>
    @endpush
@endunless
