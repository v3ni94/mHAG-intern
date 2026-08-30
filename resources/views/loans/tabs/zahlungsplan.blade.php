{{-- Zahlungsplan (Abschnitt 45): Fälligkeit, Art, SOLL, IST, offen, Status, Datenherkunft --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Zahlungsplan</span>
        <span class="small text-muted">SOLL und IST sind strikt getrennt; die Herkunft jedes IST-Wertes ist gekennzeichnet.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Fälligkeit</th>
                    <th>Art</th>
                    <th class="text-end">SOLL</th>
                    <th class="text-end">IST</th>
                    <th class="text-end">Offen</th>
                    <th>Status</th>
                    <th>Herkunft</th>
                    @if ($canRecord)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->repaymentPlanItems as $item)
                    <tr>
                        <td>{{ format_date($item->due_date) }}</td>
                        <td>{{ $item->item_type?->label() }}@if ($item->manually_adjusted) <span class="badge text-bg-light" title="SOLL-Wert wurde manuell angepasst">angepasst</span>@endif</td>
                        <td class="text-end"><x-money :amount="$item->planned_amount" /></td>
                        <td class="text-end"><x-money :amount="$item->effectiveActual()" /></td>
                        <td class="text-end {{ \App\Support\Money::isPositive($item->openAmount()) ? 'fw-semibold' : 'text-muted' }}">
                            <x-money :amount="$item->openAmount()" />
                        </td>
                        <td><x-enum-badge :enum="$item->status" /></td>
                        <td><x-origin-badge :origin="$item->origin" /></td>
                        @if ($canRecord)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#plan-edit-{{ $item->id }}" aria-expanded="false" title="IST erfassen / SOLL anpassen">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                    @if ($canRecord)
                        <tr class="collapse" id="plan-edit-{{ $item->id }}">
                            <td colspan="8" class="bg-light">
                                <form method="POST" action="{{ route('loans.schedule.update', $item) }}" class="row g-2 align-items-end p-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="return_tab" value="zahlungsplan">
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1" for="plan-status-{{ $item->id }}">Status</label>
                                        <select id="plan-status-{{ $item->id }}" name="status" class="form-select form-select-sm" required>
                                            <option value="confirmed">Bestätigt bezahlt</option>
                                            <option value="partial">Teilweise bezahlt</option>
                                            <option value="missed">Nicht bezahlt</option>
                                            <option value="late">Verspätet bezahlt</option>
                                            <option value="waived">Erlassen</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1" for="plan-actual-{{ $item->id }}">IST-Betrag (EUR)</label>
                                        <input type="text" inputmode="decimal" id="plan-actual-{{ $item->id }}" name="actual_amount"
                                               class="form-control form-control-sm" placeholder="z. B. 500,00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1" for="plan-date-{{ $item->id }}">Zahlungsdatum</label>
                                        <input type="date" id="plan-date-{{ $item->id }}" name="actual_date" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1" for="plan-planned-{{ $item->id }}">SOLL ändern (optional)</label>
                                        <input type="text" inputmode="decimal" id="plan-planned-{{ $item->id }}" name="planned_amount"
                                               class="form-control form-control-sm" placeholder="{{ \App\Support\Money::format($item->planned_amount, 'EUR', false) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1" for="plan-comment-{{ $item->id }}">Kommentar</label>
                                        <input type="text" id="plan-comment-{{ $item->id }}" name="comment" class="form-control form-control-sm"
                                               value="{{ $item->comment }}">
                                    </div>
                                    <div class="col-md-1 d-grid">
                                        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $canRecord ? 8 : 7 }}"><x-empty-state icon="bi-calendar-week" message="Noch kein Zahlungsplan vorhanden. Der Plan wird aus den Vertragsdaten erzeugt; bei Bedarf Neuberechnung ausführen." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
