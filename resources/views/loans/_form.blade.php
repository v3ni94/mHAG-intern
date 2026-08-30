{{--
    Gemeinsames Darlehensformular (Abschnitt 20 Masterprompt).
    Parameter: $loan, $entities, $loanTypes, $handlers, $isInternal, $mode ('create'|'edit')
--}}
@php
    $money = fn ($v) => $v !== null && $v !== '' ? \App\Support\Money::format($v, 'EUR', false) : '';
    $rate = fn ($v) => $v === null || $v === '' ? '' : (rtrim(rtrim(str_replace('.', ',', (string) $v), '0'), ',') ?: '0');
    $dateVal = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';
@endphp

<div class="card mb-3">
    <div class="card-header">Vertragsparteien</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="title">Bezeichnung *</label>
                <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title', $loan->title) }}" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="loan_type_id">Darlehensart</label>
                <select id="loan_type_id" name="loan_type_id" class="form-select @error('loan_type_id') is-invalid @enderror">
                    <option value="">Bitte wählen</option>
                    @foreach ($loanTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) old('loan_type_id', $loan->loan_type_id) === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('loan_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="lender_entity_id">Darlehensgeber *</label>
                <select id="lender_entity_id" name="lender_entity_id" class="form-select @error('lender_entity_id') is-invalid @enderror" required>
                    <option value="">Bitte wählen</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((string) old('lender_entity_id', $loan->lender_entity_id) === (string) $entity->id)>{{ $entity->display_name }}</option>
                    @endforeach
                </select>
                @error('lender_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="borrower_entity_id">Darlehensnehmer *</label>
                <select id="borrower_entity_id" name="borrower_entity_id" class="form-select @error('borrower_entity_id') is-invalid @enderror" required>
                    <option value="">Bitte wählen</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((string) old('borrower_entity_id', $loan->borrower_entity_id) === (string) $entity->id)>{{ $entity->display_name }}</option>
                    @endforeach
                </select>
                @error('borrower_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Vertragsdaten</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="contract_basis">Vertragsgrundlage</label>
                <input type="text" id="contract_basis" name="contract_basis" class="form-control @error('contract_basis') is-invalid @enderror"
                       value="{{ old('contract_basis', $loan->contract_basis) }}" placeholder="z. B. Darlehensvertrag vom ...">
                @error('contract_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="contract_date">Vertragsdatum</label>
                <input type="date" id="contract_date" name="contract_date" class="form-control @error('contract_date') is-invalid @enderror"
                       value="{{ old('contract_date', $dateVal($loan->contract_date)) }}">
                @error('contract_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="effective_from">Wirkungsbeginn * <x-help-icon text="Datum, ab dem das Darlehen fachlich gilt. Eine rückwirkende Erfassung ist möglich; das System berechnet dann alle Werte ab diesem Datum." /></label>
                <input type="date" id="effective_from" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror"
                       value="{{ old('effective_from', $dateVal($loan->effective_from)) }}" required>
                @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="disbursement_date">Auszahlungstag</label>
                <input type="date" id="disbursement_date" name="disbursement_date" class="form-control @error('disbursement_date') is-invalid @enderror"
                       value="{{ old('disbursement_date', $dateVal($loan->disbursement_date)) }}">
                @error('disbursement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="term_months">Laufzeit (Monate)</label>
                <input type="number" id="term_months" name="term_months" min="1" class="form-control @error('term_months') is-invalid @enderror"
                       value="{{ old('term_months', $loan->term_months) }}">
                @error('term_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="due_date">Fälligkeit</label>
                <input type="date" id="due_date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                       value="{{ old('due_date', $dateVal($loan->due_date)) }}">
                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="notice_period">Kündigungsfrist</label>
                <input type="text" id="notice_period" name="notice_period" class="form-control @error('notice_period') is-invalid @enderror"
                       value="{{ old('notice_period', $loan->notice_period) }}" placeholder="z. B. 3 Monate zum Quartalsende">
                @error('notice_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="contract_end">Vertragsende</label>
                <input type="date" id="contract_end" name="contract_end" class="form-control @error('contract_end') is-invalid @enderror"
                       value="{{ old('contract_end', $dateVal($loan->contract_end)) }}">
                @error('contract_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Beträge und Konditionen</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="principal_amount">Ursprüngliche Darlehenssumme (EUR) *</label>
                <input type="text" inputmode="decimal" id="principal_amount" name="principal_amount"
                       class="form-control @error('principal_amount') is-invalid @enderror"
                       value="{{ old('principal_amount', $money($loan->principal_amount)) }}" placeholder="z. B. 100.000,00" required>
                @error('principal_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="credit_limit">Darlehensrahmen (EUR)</label>
                <input type="text" inputmode="decimal" id="credit_limit" name="credit_limit"
                       class="form-control @error('credit_limit') is-invalid @enderror"
                       value="{{ old('credit_limit', $money($loan->credit_limit)) }}" placeholder="optional">
                @error('credit_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="currency">Währung</label>
                <input type="text" id="currency" name="currency" maxlength="3" class="form-control @error('currency') is-invalid @enderror"
                       value="{{ old('currency', $loan->currency ?? 'EUR') }}">
                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if (($mode ?? 'create') === 'create')
                <div class="col-md-4">
                    <label class="form-label" for="interest_rate">Zinssatz (% p. a.) * <x-help-icon text="Wird als erste Zeile der historisierten Zinssatz-Staffel ab Wirkungsbeginn gespeichert. Zinslos = 0." /></label>
                    <input type="text" inputmode="decimal" id="interest_rate" name="interest_rate"
                           class="form-control @error('interest_rate') is-invalid @enderror"
                           value="{{ old('interest_rate') }}" placeholder="z. B. 6 oder 3,125" required>
                    @error('interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif

            <div class="col-md-4">
                <label class="form-label" for="interest_method">Zinsmethode *</label>
                <select id="interest_method" name="interest_method" class="form-select @error('interest_method') is-invalid @enderror" required>
                    @foreach (\App\Enums\InterestMethod::cases() as $method)
                        <option value="{{ $method->value }}" @selected(old('interest_method', $loan->interest_method?->value) === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
                @error('interest_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="interest_frequency">Zinsfälligkeit *</label>
                <select id="interest_frequency" name="interest_frequency" class="form-select @error('interest_frequency') is-invalid @enderror" required>
                    @foreach (\App\Enums\InterestFrequency::cases() as $frequency)
                        <option value="{{ $frequency->value }}" @selected(old('interest_frequency', $loan->interest_frequency?->value) === $frequency->value)>{{ $frequency->label() }}</option>
                    @endforeach
                </select>
                @error('interest_frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="repayment_model">Tilgungsmodell *</label>
                <select id="repayment_model" name="repayment_model" class="form-select @error('repayment_model') is-invalid @enderror" required>
                    @foreach (\App\Enums\RepaymentModel::cases() as $model)
                        <option value="{{ $model->value }}" @selected(old('repayment_model', $loan->repayment_model?->value) === $model->value)>{{ $model->label() }}</option>
                    @endforeach
                </select>
                @error('repayment_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <div class="form-check mt-4">
                    <input type="hidden" name="default_interest_enabled" value="0">
                    <input type="checkbox" id="default_interest_enabled" name="default_interest_enabled" value="1"
                           class="form-check-input" @checked(old('default_interest_enabled', $loan->default_interest_enabled))>
                    <label class="form-check-label" for="default_interest_enabled">Verzugszinsen aktivieren</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_interest_rate">Verzugszinssatz (% p. a.)</label>
                <input type="text" inputmode="decimal" id="default_interest_rate" name="default_interest_rate"
                       class="form-control @error('default_interest_rate') is-invalid @enderror"
                       value="{{ old('default_interest_rate', $rate($loan->default_interest_rate)) }}" placeholder="nur wenn vertraglich vereinbart">
                @error('default_interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_interest_start">Verzugsbeginn</label>
                <input type="date" id="default_interest_start" name="default_interest_start"
                       class="form-control @error('default_interest_start') is-invalid @enderror"
                       value="{{ old('default_interest_start', $loan->default_interest_start?->format('Y-m-d')) }}">
                @error('default_interest_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_interest_basis">Berechnungsgrundlage</label>
                <select id="default_interest_basis" name="default_interest_basis"
                        class="form-select @error('default_interest_basis') is-invalid @enderror">
                    @foreach (\App\Services\Loans\DefaultInterestService::BASIS_LABELS as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('default_interest_basis', $loan->default_interest_basis ?: \App\Services\Loans\DefaultInterestService::BASIS_OVERDUE_TOTAL) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('default_interest_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_interest_method">Zinsmethode der Verzugszinsen</label>
                <select id="default_interest_method" name="default_interest_method"
                        class="form-select @error('default_interest_method') is-invalid @enderror">
                    <option value="">wie Darlehen</option>
                    @foreach (\App\Enums\InterestMethod::cases() as $method)
                        <option value="{{ $method->value }}"
                            @selected(old('default_interest_method', $loan->default_interest_method?->value) === $method->value)>
                            {{ $method->label() }}
                        </option>
                    @endforeach
                </select>
                @error('default_interest_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_interest_mode">Aktivierung</label>
                <select id="default_interest_mode" name="default_interest_mode"
                        class="form-select @error('default_interest_mode') is-invalid @enderror">
                    @foreach (\App\Services\Loans\DefaultInterestService::MODE_LABELS as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('default_interest_mode', $loan->default_interest_mode ?: \App\Services\Loans\DefaultInterestService::MODE_MANUAL) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('default_interest_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <p class="form-text mb-0">
                    Verzugszinsen werden ausschließlich nach den hier erfassten fachlichen Vorgaben berechnet.
                    Ohne Verzugszinssatz und ohne Verzugsbeginn berechnet und bucht das System nichts;
                    ein gesetzlicher Satz wird nicht unterstellt.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Organisation</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="handler_user_id">Sachbearbeiter</label>
                <select id="handler_user_id" name="handler_user_id" class="form-select @error('handler_user_id') is-invalid @enderror">
                    <option value="">Bitte wählen</option>
                    @foreach ($handlers as $handler)
                        <option value="{{ $handler->id }}" @selected((string) old('handler_user_id', $loan->handler_user_id) === (string) $handler->id)>{{ $handler->name }}</option>
                    @endforeach
                </select>
                @error('handler_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="project">Projekt</label>
                <input type="text" id="project" name="project" class="form-control @error('project') is-invalid @enderror"
                       value="{{ old('project', $loan->project) }}">
                @error('project')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="cost_center">Kostenstelle</label>
                <input type="text" id="cost_center" name="cost_center" class="form-control @error('cost_center') is-invalid @enderror"
                       value="{{ old('cost_center', $loan->cost_center) }}">
                @error('cost_center')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if ($isInternal)
                <div class="col-md-4">
                    <label class="form-label" for="risk_rating">Risiko-Einstufung (intern) <x-help-icon text="Manuelle Einschätzung, nur für interne Rollen sichtbar. Keine automatische Bonitätsbewertung." /></label>
                    <select id="risk_rating" name="risk_rating" class="form-select @error('risk_rating') is-invalid @enderror">
                        <option value="">Keine Angabe</option>
                        @foreach (\App\Enums\RiskRating::cases() as $rating)
                            <option value="{{ $rating->value }}" @selected(old('risk_rating', $loan->risk_rating?->value) === $rating->value)>{{ $rating->label() }}</option>
                        @endforeach
                    </select>
                    @error('risk_rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="internal_notes">Interne Notizen (nur für interne Rollen)</label>
                    <textarea id="internal_notes" name="internal_notes" rows="3" class="form-control @error('internal_notes') is-invalid @enderror">{{ old('internal_notes', $loan->internal_notes) }}</textarea>
                    @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
    </div>
</div>
