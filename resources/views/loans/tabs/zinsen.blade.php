{{-- Zinsen: Übersichtswerte und Zins-Positionen --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <x-kpi-card label="Zinsen SOLL (bis heute)" :value="format_money($balances['interest_charged'] ?? '0.00')" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Zinsen IST (bestätigt)" :value="format_money($balances['interest_confirmed'] ?? '0.00')" severity="success" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Zinsen angenommen" :value="format_money($balances['interest_assumed'] ?? '0.00')" severity="info"
                    hint="Systemseitig angenommen, nicht bestätigt" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Offene Zinsen" :value="format_money($balances['interest_open'] ?? '0.00')"
                    :severity="\App\Support\Money::isPositive($balances['interest_open'] ?? '0.00') ? 'warning' : null" />
    </div>
</div>

<div class="card">
    <div class="card-header">Zins-Positionen</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Fälligkeit</th>
                    <th class="text-end">SOLL</th>
                    <th class="text-end">IST</th>
                    <th class="text-end">Offen</th>
                    <th>Status</th>
                    <th>Herkunft</th>
                    <th>Kommentar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($interestItems as $item)
                    <tr>
                        <td>{{ format_date($item->due_date) }}</td>
                        <td class="text-end"><x-money :amount="$item->planned_amount" /></td>
                        <td class="text-end"><x-money :amount="$item->effectiveActual()" /></td>
                        <td class="text-end {{ \App\Support\Money::isPositive($item->openAmount()) ? 'fw-semibold' : 'text-muted' }}">
                            <x-money :amount="$item->openAmount()" />
                        </td>
                        <td><x-enum-badge :enum="$item->status" /></td>
                        <td><x-origin-badge :origin="$item->origin" /></td>
                        <td class="small text-muted">{{ $item->comment }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="bi-percent" message="Keine Zins-Positionen vorhanden." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Die IST-Erfassung je Monat erfolgt im Reiter Soll/Ist.
    </div>
</div>
