@extends('layouts.app')

@section('title', 'Zahlung erfassen')

@section('content')
    <x-page-header title="Zahlung erfassen" label="Finanzen · Zahlungen">
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('payments.store') }}">
        @csrf

        <div class="card mb-3">
            <div class="card-header">Zahlungsdaten</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="loan_id">Darlehen *</label>
                        <select id="loan_id" name="loan_id" class="form-select @error('loan_id') is-invalid @enderror" required>
                            <option value="">Bitte wählen</option>
                            @foreach ($loans as $loan)
                                <option value="{{ $loan->id }}" @selected((string) old('loan_id', $selectedLoanId) === (string) $loan->id)>
                                    {{ $loan->loan_number }} · {{ $loan->title }} ({{ $loan->borrower?->display_name }} an {{ $loan->lender?->display_name }})
                                </option>
                            @endforeach
                        </select>
                        @error('loan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Zahler und Empfänger werden aus dem Darlehen übernommen, sofern nicht abweichend erfasst.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="amount">Betrag (EUR) *</label>
                        <input type="text" inputmode="decimal" id="amount" name="amount"
                               class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" placeholder="z. B. 1.234,56" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="direction">Richtung *</label>
                        <select id="direction" name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                            <option value="incoming" @selected(old('direction', 'incoming') === 'incoming')>Eingang (Zahlung an den Darlehensgeber)</option>
                            <option value="outgoing" @selected(old('direction') === 'outgoing')>Ausgang</option>
                        </select>
                        @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_date">Zahlungsdatum *</label>
                        <input type="date" id="payment_date" name="payment_date"
                               class="form-control @error('payment_date') is-invalid @enderror"
                               value="{{ old('payment_date', now()->toDateString()) }}" required>
                        @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="value_date">Valutadatum</label>
                        <input type="date" id="value_date" name="value_date"
                               class="form-control @error('value_date') is-invalid @enderror" value="{{ old('value_date') }}">
                        @error('value_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="origin">Herkunft *</label>
                        <select id="origin" name="origin" class="form-select @error('origin') is-invalid @enderror" required>
                            <option value="manual_entered" @selected(old('origin', 'manual_entered') === 'manual_entered')>Manuell erfasst</option>
                            <option value="manual_confirmed" @selected(old('origin') === 'manual_confirmed')>Manuell bestätigt</option>
                            <option value="bank_import" @selected(old('origin') === 'bank_import')>Bankseitig bestätigt</option>
                        </select>
                        @error('origin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="reference">Referenz</label>
                        <input type="text" id="reference" name="reference" class="form-control @error('reference') is-invalid @enderror"
                               value="{{ old('reference') }}">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="purpose">Verwendungszweck</label>
                        <input type="text" id="purpose" name="purpose" class="form-control @error('purpose') is-invalid @enderror"
                               value="{{ old('purpose') }}">
                        @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="note">Notiz</label>
                        <input type="text" id="note" name="note" class="form-control @error('note') is-invalid @enderror"
                               value="{{ old('note') }}">
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Zahlungsverkehr: beide Kontoseiten (Abschnitt 46) --}}
        <div class="card mb-3">
            <div class="card-header">
                Konten
                <x-help-icon text="Von welchem Konto gezahlt wurde und auf welches. Beide Angaben sind freiwillig, weil sie bei nachträglich erfassten Altvorgängen häufig nicht mehr bekannt sind. Es können nur Konten der jeweiligen Partei gewählt werden." />
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="payer_bank_account_id">Gezahlt von Konto</label>
                        <select id="payer_bank_account_id" name="payer_bank_account_id"
                                class="form-select @error('payer_bank_account_id') is-invalid @enderror"
                                data-selected="{{ old('payer_bank_account_id') }}">
                            <option value="">ohne Angabe</option>
                        </select>
                        @error('payer_bank_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="payer_bank_account_hint"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payee_bank_account_id">Gezahlt auf Konto</label>
                        <select id="payee_bank_account_id" name="payee_bank_account_id"
                                class="form-select @error('payee_bank_account_id') is-invalid @enderror"
                                data-selected="{{ old('payee_bank_account_id') }}">
                            <option value="">ohne Angabe</option>
                        </select>
                        @error('payee_bank_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="payee_bank_account_hint"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Verrechnung</div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input type="hidden" name="allocate_manually" value="0">
                    <input type="checkbox" id="allocate_manually" name="allocate_manually" value="1"
                           class="form-check-input" @checked(old('allocate_manually'))>
                    <label class="form-check-label" for="allocate_manually">
                        Manuell aufteilen (sonst gilt die konfigurierte Verrechnungsreihenfolge: Kosten, Gebühren, Verzugszinsen, Zinsen, Kapital)
                    </label>
                </div>
                @error('alloc')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                <div class="row g-2">
                    @foreach (['costs' => 'Kosten', 'fees' => 'Gebühren', 'default_interest' => 'Verzugszinsen', 'interest' => 'Vertragszinsen', 'principal' => 'Kapital', 'other' => 'Sonstige'] as $bucket => $label)
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="alloc-{{ $bucket }}">{{ $label }}</label>
                            <input type="text" inputmode="decimal" id="alloc-{{ $bucket }}" name="alloc[{{ $bucket }}]"
                                   class="form-control form-control-sm @error('alloc.'.$bucket) is-invalid @enderror"
                                   value="{{ old('alloc.'.$bucket) }}" placeholder="0,00">
                            @error('alloc.'.$bucket)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Zahlung erfassen</button>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection

@push('scripts')
    {{--
        Kontoauswahl je Partei: Die wählbaren Konten hängen vom gewählten
        Darlehen ab (Zahler = Darlehensnehmer, Empfänger = Darlehensgeber).
        Ohne hinterlegtes Konto erscheint ein Hinweis mit Verweis auf die
        Akte der Partei (Reiter Bankkonten). Kein Framework, reines JavaScript.
    --}}
    <script>
        (function () {
            const accountsByEntity = @json($accountsByEntity);
            const loanParties = @json($loanParties);

            const loanSelect = document.getElementById('loan_id');
            const targets = [
                { side: 'payer', select: document.getElementById('payer_bank_account_id'), hint: document.getElementById('payer_bank_account_hint') },
                { side: 'payee', select: document.getElementById('payee_bank_account_id'), hint: document.getElementById('payee_bank_account_hint') }
            ];

            function fill(target, parties) {
                const select = target.select;
                const hint = target.hint;
                if (!select) { return; }

                const wanted = select.dataset.selected || select.value || '';
                select.innerHTML = '';
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'ohne Angabe';
                select.appendChild(empty);

                if (!parties) {
                    hint.textContent = 'Bitte zuerst ein Darlehen wählen.';
                    return;
                }

                const entityId = parties[target.side + '_entity_id'];
                const name = parties[target.side + '_name'] || 'die Partei';
                const url = parties[target.side + '_url'];
                const accounts = (entityId && accountsByEntity[entityId]) ? accountsByEntity[entityId] : [];

                accounts.forEach(function (account) {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = account.label;
                    if (String(account.id) === String(wanted)) { option.selected = true; }
                    select.appendChild(option);
                });

                if (accounts.length === 0) {
                    hint.innerHTML = 'Für ' + name + ' ist kein Bankkonto hinterlegt.'
                        + (url ? ' <a href="' + url + '">Konten in der Akte pflegen</a>.' : '');
                } else {
                    hint.textContent = 'Auswählbar sind ausschließlich Konten von ' + name + '.';
                }
            }

            function refresh() {
                const parties = loanSelect ? loanParties[loanSelect.value] : null;
                targets.forEach(function (target) { fill(target, parties); });
            }

            if (loanSelect) { loanSelect.addEventListener('change', refresh); }
            refresh();
        })();
    </script>
@endpush
