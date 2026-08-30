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
                    <th>Konten (von / auf)</th>
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
                        <td class="small">
                            @include('payments._bank-account', [
                                'account' => $disbursement->sourceBankAccount ?: $disbursement->bankAccount,
                                'canSeeAccounts' => $canSeeAccounts,
                                'visibleEntityIds' => $visibleEntityIds,
                            ])
                            <hr class="my-1">
                            @include('payments._bank-account', [
                                'account' => $disbursement->targetBankAccount,
                                'canSeeAccounts' => $canSeeAccounts,
                                'visibleEntityIds' => $visibleEntityIds,
                            ])
                        </td>
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
                            <td colspan="9" class="bg-light">
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
                            <td colspan="9" class="bg-light">
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
                    <tr><td colspan="9"><x-empty-state icon="bi-cash-coin" message="Keine Auszahlungen vorhanden." /></td></tr>
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

                {{-- Beide Kontoseiten (Abschnitt 31), freiwillig: bei Altvorgaengen oft unbekannt --}}
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="disb-new-source">Ausgezahlt von Konto (Darlehensgeber)</label>
                    @if ($lenderAccounts->isEmpty())
                        <div class="form-text mb-1">
                            Für {{ $loan->lender?->display_name ?: 'den Darlehensgeber' }} ist kein Bankkonto hinterlegt.
                            @php($lenderRoute = $loan->lender?->type === \App\Enums\EntityType::Person ? 'persons.show' : 'companies.show')
                            @if ($loan->lender && \Illuminate\Support\Facades\Route::has($lenderRoute))
                                <a href="{{ route($lenderRoute, [$loan->lender_entity_id, 'tab' => 'bankkonten']) }}">Konten in der Akte pflegen</a>.
                            @endif
                        </div>
                    @else
                        <select id="disb-new-source" name="source_bank_account_id" class="form-select form-select-sm">
                            <option value="">ohne Angabe</option>
                            @foreach ($lenderAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('source_bank_account_id') === (string) $account->id)>
                                    {{ $account->bank_name ?: 'Bank ohne Angabe' }} · {{ \App\Http\Controllers\PaymentController::formatIban($account->iban) }} · {{ $account->account_holder }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @error('source_bank_account_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="disb-new-target">Ausgezahlt auf Konto (Darlehensnehmer)</label>
                    @if ($borrowerAccounts->isEmpty())
                        <div class="form-text mb-1">
                            Für {{ $loan->borrower?->display_name ?: 'den Darlehensnehmer' }} ist kein Bankkonto hinterlegt.
                            @php($borrowerRoute = $loan->borrower?->type === \App\Enums\EntityType::Person ? 'persons.show' : 'companies.show')
                            @if ($loan->borrower && \Illuminate\Support\Facades\Route::has($borrowerRoute))
                                <a href="{{ route($borrowerRoute, [$loan->borrower_entity_id, 'tab' => 'bankkonten']) }}">Konten in der Akte pflegen</a>.
                            @endif
                        </div>
                    @else
                        <select id="disb-new-target" name="target_bank_account_id" class="form-select form-select-sm">
                            <option value="">ohne Angabe</option>
                            @foreach ($borrowerAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('target_bank_account_id') === (string) $account->id)>
                                    {{ $account->bank_name ?: 'Bank ohne Angabe' }} · {{ \App\Http\Controllers\PaymentController::formatIban($account->iban) }} · {{ $account->account_holder }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @error('target_bank_account_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </form>
        </div>
    @endif
</div>
