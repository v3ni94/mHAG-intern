{{--
    Soll/Ist-Monatsübersicht (Abschnitt 28): Zins-Positionen je Monat mit
    Inline-Erfassung von IST-Betrag, Status, Datum und Kommentar.
--}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Soll/Ist-Monatsübersicht (Zinsen)</span>
        <span class="small text-muted">Systemseitig angenommene Zahlungen sind keine bestätigten Zahlungen; die Herkunft ist stets gekennzeichnet.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Monat</th>
                    <th class="text-end">Soll</th>
                    <th class="text-end">Ist</th>
                    <th class="text-end">Offen</th>
                    <th>Status</th>
                    <th>Herkunft</th>
                    @if ($canRecord)<th style="min-width: 540px;">Erfassung</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($interestItems as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->due_date?->format('m/Y') }} <span class="text-muted small">({{ format_date($item->due_date) }})</span></td>
                        <td class="text-end"><x-money :amount="$item->planned_amount" /></td>
                        <td class="text-end"><x-money :amount="$item->effectiveActual()" /></td>
                        <td class="text-end {{ \App\Support\Money::isPositive($item->openAmount()) ? 'fw-semibold' : 'text-muted' }}">
                            <x-money :amount="$item->openAmount()" />
                        </td>
                        <td><x-enum-badge :enum="$item->status" /></td>
                        <td><x-origin-badge :origin="$item->origin" /></td>
                        @if ($canRecord)
                            <td>
                                <form method="POST" action="{{ route('loans.schedule.update', $item) }}" class="d-flex flex-wrap gap-1 align-items-center">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="return_tab" value="soll-ist">
                                    <label class="visually-hidden" for="si-amount-{{ $item->id }}">IST-Betrag</label>
                                    <input type="text" inputmode="decimal" id="si-amount-{{ $item->id }}" name="actual_amount"
                                           class="form-control form-control-sm" style="width: 110px;"
                                           placeholder="IST-Betrag" value="{{ $item->actual_amount !== null ? \App\Support\Money::format($item->actual_amount, 'EUR', false) : '' }}">
                                    <label class="visually-hidden" for="si-status-{{ $item->id }}">Status</label>
                                    <select id="si-status-{{ $item->id }}" name="status" class="form-select form-select-sm" style="width: 160px;" required>
                                        <option value="confirmed" @selected($item->status === \App\Enums\RepaymentItemStatus::Confirmed)>Bestätigt bezahlt</option>
                                        <option value="partial" @selected($item->status === \App\Enums\RepaymentItemStatus::Partial)>Teilweise bezahlt</option>
                                        <option value="missed" @selected($item->status === \App\Enums\RepaymentItemStatus::Missed)>Nicht bezahlt</option>
                                        <option value="late" @selected($item->status === \App\Enums\RepaymentItemStatus::Late)>Verspätet bezahlt</option>
                                        <option value="waived" @selected($item->status === \App\Enums\RepaymentItemStatus::Waived)>Erlassen</option>
                                    </select>
                                    <label class="visually-hidden" for="si-date-{{ $item->id }}">Zahlungsdatum</label>
                                    <input type="date" id="si-date-{{ $item->id }}" name="actual_date" class="form-control form-control-sm" style="width: 145px;"
                                           value="{{ $item->actual_date?->format('Y-m-d') }}">
                                    <label class="visually-hidden" for="si-comment-{{ $item->id }}">Kommentar</label>
                                    <input type="text" id="si-comment-{{ $item->id }}" name="comment" class="form-control form-control-sm" style="width: 140px;"
                                           placeholder="Kommentar" value="{{ $item->comment }}">
                                    <button type="submit" class="btn btn-primary btn-sm" title="IST-Wert speichern und neu berechnen">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canRecord ? 7 : 6 }}"><x-empty-state icon="bi-calendar-month" message="Keine Zins-Positionen vorhanden." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Nach jeder Erfassung berechnet das System alle Folgewerte automatisch neu (offene Zinsen, Forderungsstand, Stichtagswerte).
    </div>
</div>
