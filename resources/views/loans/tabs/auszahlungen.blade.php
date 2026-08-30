{{-- Auszahlungen (Abschnitte 31/32): SOLL und IST getrennt, Status und Herkunft sichtbar --}}
<div class="card">
    <div class="card-header">Auszahlungen</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Geplantes Datum</th>
                    <th class="text-end">SOLL-Betrag</th>
                    <th>Tatsächliches Datum</th>
                    <th class="text-end">IST-Betrag</th>
                    <th>Status</th>
                    <th>Herkunft</th>
                    <th>Referenz</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->disbursements as $disbursement)
                    @php($isOpen = in_array($disbursement->status, [\App\Enums\DisbursementStatus::Planned, \App\Enums\DisbursementStatus::Assumed], true))
                    <tr>
                        <td>{{ format_date($disbursement->planned_date) }}</td>
                        <td class="text-end"><x-money :amount="$disbursement->planned_amount" /></td>
                        <td>{{ $disbursement->actual_date ? format_date($disbursement->actual_date) : '' }}</td>
                        <td class="text-end">@if ($disbursement->actual_amount !== null)<x-money :amount="$disbursement->actual_amount" />@endif</td>
                        <td><x-enum-badge :enum="$disbursement->status" /></td>
                        <td><x-origin-badge :origin="$disbursement->origin" /></td>
                        <td class="small">{{ $disbursement->reference }}</td>
                        <td class="text-end">
                            @if ($canRecord && $isOpen)
                                <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#disb-confirm-{{ $disbursement->id }}" title="Bestätigen">
                                    <i class="bi bi-check-lg"></i> Bestätigen
                                </button>
                                <x-confirm-form :action="route('loans.disbursements.fail', $disbursement)" method="POST"
                                                confirm="Auszahlung als nicht erfolgt markieren? IST wird 0, Folgewerte werden korrigiert."
                                                label="Nicht erfolgt" icon="bi-x-lg" class="btn btn-sm btn-outline-warning" />
                            @endif
                            @if ($canCancelPayments && $disbursement->status !== \App\Enums\DisbursementStatus::Cancelled)
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#disb-cancel-{{ $disbursement->id }}" title="Stornieren">
                                    <i class="bi bi-x-octagon"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if ($canRecord && $isOpen)
                        <tr class="collapse" id="disb-confirm-{{ $disbursement->id }}">
                            <td colspan="8" class="bg-light">
                                <form method="POST" action="{{ route('loans.disbursements.confirm', $disbursement) }}" class="row g-2 align-items-end p-2">
                                    @csrf
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1" for="disb-amount-{{ $disbursement->id }}">IST-Betrag (EUR)</label>
                                        <input type="text" inputmode="decimal" id="disb-amount-{{ $disbursement->id }}" name="actual_amount"
                                               class="form-control form-control-sm"
                                               value="{{ \App\Support\Money::format($disbursement->planned_amount, 'EUR', false) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1" for="disb-date-{{ $disbursement->id }}">Tatsächliches Datum</label>
                                        <input type="date" id="disb-date-{{ $disbursement->id }}" name="actual_date" class="form-control form-control-sm"
                                               value="{{ $disbursement->planned_date?->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1" for="disb-origin-{{ $disbursement->id }}">Herkunft</label>
                                        <select id="disb-origin-{{ $disbursement->id }}" name="origin" class="form-select form-select-sm" required>
                                            <option value="manual_confirmed">Manuell bestätigt</option>
                                            <option value="manual_entered">Manuell erfasst</option>
                                            <option value="bank_import">Bankseitig bestätigt</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-grid">
                                        <button type="submit" class="btn btn-success btn-sm">Auszahlung bestätigen</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                    @if ($canCancelPayments && $disbursement->status !== \App\Enums\DisbursementStatus::Cancelled)
                        <tr class="collapse" id="disb-cancel-{{ $disbursement->id }}">
                            <td colspan="8" class="bg-light">
                                <form method="POST" action="{{ route('loans.disbursements.cancel', $disbursement) }}" class="row g-2 align-items-end p-2">
                                    @csrf
                                    <div class="col-md-9">
                                        <label class="form-label small mb-1" for="disb-reason-{{ $disbursement->id }}">Stornogrund (Pflicht)</label>
                                        <input type="text" id="disb-reason-{{ $disbursement->id }}" name="reason" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-3 d-grid">
                                        <button type="submit" class="btn btn-danger btn-sm">Stornieren</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8"><x-empty-state icon="bi-cash-coin" message="Keine Auszahlungen vorhanden." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canUpdate)
        <div class="card-footer">
            <form method="POST" action="{{ route('loans.disbursements.store', $loan) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="disb-new-amount">Geplanter Betrag (EUR)</label>
                    <input type="text" inputmode="decimal" id="disb-new-amount" name="planned_amount" class="form-control form-control-sm"
                           placeholder="z. B. 50.000,00" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="disb-new-date">Geplantes Datum</label>
                    <input type="date" id="disb-new-date" name="planned_date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="disb-new-reference">Referenz</label>
                    <input type="text" id="disb-new-reference" name="reference" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Auszahlung planen</button>
                </div>
            </form>
        </div>
    @endif
</div>
