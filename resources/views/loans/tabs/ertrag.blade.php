{{-- Ertrag und Rendite (Anforderung vom 30.08.2026) --}}
@php
    $zeitraum = $yield['period_from']
        ? format_date($yield['period_from']).' bis '.format_date($yield['period_to']).' ('.$yield['period_days'].' Tage)'
        : 'kein gebundenes Kapital im Betrachtungszeitraum';
    $prozent = fn (?string $wert) => $wert === null ? null : number_format((float) $wert, 4, ',', '.').' %';
@endphp

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <x-kpi-card label="Ertrag (belegt)" :value="format_money($yield['yield_confirmed'])"
                    help="Bestätigte Zinszahlungen, dem Kapital zugeschriebene Zinsen und bestätigte Gebührenzahlungen. Systemseitig angenommene Zahlungen sind hier NICHT enthalten." />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Ertrag einschließlich Annahmen" :value="format_money($yield['yield_total'])"
                    help="Zusätzlich die systemseitig angenommenen Zahlungen. Eine Annahme ist kein Nachweis; beide Werte werden deshalb getrennt geführt." />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Durchschnittlich gebundenes Kapital" :value="format_money($yield['average_capital'])"
                    help="Zeitgewichteter Mittelwert des offenen Kapitals: Summe aus Kapital mal Tage, geteilt durch die Gesamttage." />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Rendite p. a. (belegt)"
                    :value="$prozent($yield['return_pa']) ?? 'nicht berechenbar'"
                    help="Belegter Ertrag, geteilt durch das durchschnittlich gebundene Kapital, hochgerechnet über den Jahresbruchteil der Zinsmethode." />
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        Zusammensetzung des Ertrags zum {{ format_date($yield['as_of']) }}
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Bestandteil</th>
                    <th class="text-end">Betrag</th>
                    <th>Nachweislage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Vereinnahmte Zinsen</td>
                    <td class="text-end"><x-money :amount="$yield['interest_confirmed']" :currency="$loan->currency" /></td>
                    <td class="small text-muted">bestätigte Zahlungen</td>
                </tr>
                <tr>
                    <td>Kapitalisierte Zinsen</td>
                    <td class="text-end"><x-money :amount="$yield['interest_capitalized']" :currency="$loan->currency" /></td>
                    <td class="small text-muted">dem valutierten Betrag zugeschrieben, gebucht</td>
                </tr>
                <tr>
                    <td>Vereinnahmte Gebühren</td>
                    <td class="text-end"><x-money :amount="$yield['fees_confirmed']" :currency="$loan->currency" /></td>
                    <td class="small text-muted">bestätigte Zahlungen</td>
                </tr>
                <tr class="fw-semibold border-top">
                    <td>Ertrag insgesamt (belegt)</td>
                    <td class="text-end"><x-money :amount="$yield['yield_confirmed']" :currency="$loan->currency" /></td>
                    <td></td>
                </tr>
                <tr>
                    <td>Systemseitig angenommene Zins- und Gebührenzahlungen</td>
                    <td class="text-end"><x-money :amount="$yield['yield_assumed']" :currency="$loan->currency" /></td>
                    <td class="small text-muted">
                        <x-enum-badge :enum="\App\Enums\PaymentOrigin::Assumed" />
                    </td>
                </tr>
                <tr class="fw-semibold">
                    <td>Ertrag einschließlich Annahmen</td>
                    <td class="text-end"><x-money :amount="$yield['yield_total']" :currency="$loan->currency" /></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Rechenweg der Rendite</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6">Betrachtungszeitraum</dt>
                    <dd class="col-6">{{ $zeitraum }}</dd>
                    <dt class="col-6">Zinsmethode</dt>
                    <dd class="col-6">{{ $yield['day_count_label'] }}</dd>
                    <dt class="col-6">Jahresbruchteil</dt>
                    <dd class="col-6">{{ number_format((float) $yield['year_fraction'], 6, ',', '.') }}</dd>
                </dl>

                @if ($yield['return_pa'] !== null)
                    <hr>
                    <p class="mb-1">
                        Rendite p. a. (belegt) = {{ format_money($yield['yield_confirmed']) }}
                        / {{ format_money($yield['average_capital']) }}
                        / {{ number_format((float) $yield['year_fraction'], 6, ',', '.') }}
                        = <strong>{{ $prozent($yield['return_pa']) }}</strong>
                    </p>
                    <p class="mb-0">
                        Rendite p. a. einschließlich Annahmen = {{ format_money($yield['yield_total']) }}
                        / {{ format_money($yield['average_capital']) }}
                        / {{ number_format((float) $yield['year_fraction'], 6, ',', '.') }}
                        = <strong>{{ $prozent($yield['return_pa_total']) ?? 'nicht berechenbar' }}</strong>
                    </p>
                @else
                    <hr>
                    <p class="mb-0 text-muted">
                        Ohne gebundenes Kapital im Betrachtungszeitraum wird keine Rendite ausgewiesen.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                Effektivrendite (interner Zinsfuß)
                <x-help-icon text="Rechnerische Kennzahl aus den tatsächlichen Zahlungsströmen: Auszahlungen negativ, Zahlungseingänge positiv, Restforderung zum Stichtag als Schlussbetrag. Ermittelt durch Intervallhalbierung. Keine Bonitäts- oder Wertaussage, kein Vergleich mit Marktzinsen, keine Prognose." />
            </div>
            <div class="card-body">
                @if ($yield['irr'] !== null)
                    <div class="fs-4 fw-semibold mb-2">{{ $prozent($yield['irr']) }} p. a.</div>
                @else
                    <div class="fw-semibold mb-2">nicht berechenbar</div>
                    <p class="small text-muted mb-3">{{ $yield['irr_note'] }}</p>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Vorgang</th>
                                <th class="text-end">Zahlungsstrom</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($yield['cash_flows'] as $flow)
                                <tr>
                                    <td>{{ format_date($flow['date']) }}</td>
                                    <td class="small">{{ $flow['label'] }}</td>
                                    <td class="text-end">
                                        <x-money :amount="$flow['amount']" :currency="$loan->currency" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-empty-state icon="bi-graph-up" message="Noch keine Zahlungsströme erfasst." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="small text-muted mt-3 mb-0">
    Die Kennzahlen sind rechnerische Auswertungen der erfassten Daten. Sie enthalten keine
    Bewertung der Forderung und keine Prognose.
</p>
