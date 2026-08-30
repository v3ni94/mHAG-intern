{{-- Reiter Übersicht: Vertragsdaten, Zinssatz-Staffel, Risiko (intern) --}}
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Vertragsdaten</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Darlehensnummer</dt><dd class="col-7">{{ $loan->loan_number }}</dd>
                    <dt class="col-5">Bezeichnung</dt><dd class="col-7">{{ $loan->title ?: 'ohne' }}</dd>
                    <dt class="col-5">Darlehensart</dt><dd class="col-7">{{ $loan->loanType?->name ?: 'ohne' }}</dd>
                    <dt class="col-5">Vertragsgrundlage</dt><dd class="col-7">{{ $loan->contract_basis ?: 'ohne' }}</dd>
                    <dt class="col-5">Vertragsdatum</dt><dd class="col-7">{{ $loan->contract_date ? format_date($loan->contract_date) : 'ohne' }}</dd>
                    <dt class="col-5">Wirkungsbeginn</dt><dd class="col-7">{{ format_date($loan->effective_from) }}</dd>
                    <dt class="col-5">Auszahlungstag</dt><dd class="col-7">{{ $loan->disbursement_date ? format_date($loan->disbursement_date) : 'ohne' }}</dd>
                    <dt class="col-5">Laufzeit</dt><dd class="col-7">{{ $loan->term_months ? $loan->term_months.' Monate' : 'ohne' }}</dd>
                    <dt class="col-5">Fälligkeit</dt><dd class="col-7">{{ $loan->due_date ? format_date($loan->due_date) : 'ohne' }}</dd>
                    <dt class="col-5">Kündigungsfrist</dt><dd class="col-7">{{ $loan->notice_period ?: 'ohne' }}</dd>
                    <dt class="col-5">Vertragsende</dt><dd class="col-7">{{ $loan->contract_end ? format_date($loan->contract_end) : 'ohne' }}</dd>
                    <dt class="col-5">Darlehenssumme</dt><dd class="col-7"><x-money :amount="$loan->principal_amount" :currency="$loan->currency" /></dd>
                    <dt class="col-5">Darlehensrahmen</dt><dd class="col-7">@if ($loan->credit_limit)<x-money :amount="$loan->credit_limit" :currency="$loan->currency" />@else ohne @endif</dd>
                    <dt class="col-5">Währung</dt><dd class="col-7">{{ $loan->currency }}</dd>
                    <dt class="col-5">Zinsmethode</dt><dd class="col-7">{{ $loan->interest_method?->label() }}</dd>
                    <dt class="col-5">Zinsfälligkeit</dt><dd class="col-7">{{ $loan->interest_frequency?->label() }}</dd>
                    <dt class="col-5">Tilgungsmodell</dt><dd class="col-7">{{ $loan->repayment_model?->label() }}</dd>
                    <dt class="col-5">Verzugszinsen</dt>
                    <dd class="col-7">
                        @if ($loan->default_interest_enabled)
                            aktiviert{{ $loan->default_interest_rate !== null ? ', '.format_percent($loan->default_interest_rate) : '' }}
                        @else
                            nicht aktiviert
                        @endif
                    </dd>
                    <dt class="col-5">Sachbearbeiter</dt><dd class="col-7">{{ $loan->handler?->name ?: 'ohne' }}</dd>
                    <dt class="col-5">Projekt</dt><dd class="col-7">{{ $loan->project ?: 'ohne' }}</dd>
                    <dt class="col-5">Kostenstelle</dt><dd class="col-7">{{ $loan->cost_center ?: 'ohne' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Zinssatz-Staffel <x-help-icon text="Historisierte Zinssätze. Mehrere Zeilen bilden einen Staffelzins ab; Änderungen lösen automatisch eine Neuberechnung aus." /></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Gültig ab</th>
                            <th>Gültig bis</th>
                            <th class="text-end">Zinssatz p. a.</th>
                            <th>Notiz</th>
                            @if ($canUpdate)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loan->interestTerms as $term)
                            <tr>
                                <td>{{ format_date($term->valid_from) }}</td>
                                <td>{{ $term->valid_until ? format_date($term->valid_until) : 'offen' }}</td>
                                <td class="text-end">{{ format_percent($term->rate) }}</td>
                                <td class="small text-muted">{{ $term->note }}</td>
                                @if ($canUpdate)
                                    <td class="text-end">
                                        <x-confirm-form :action="route('loans.interest-terms.destroy', [$loan, $term])" method="DELETE"
                                                        confirm="Zinssatz-Zeile wirklich entfernen? Es wird neu berechnet."
                                                        label="" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canUpdate ? 5 : 4 }}"><x-empty-state icon="bi-percent" message="Noch kein Zinssatz erfasst." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canUpdate)
                <div class="card-footer">
                    <form method="POST" action="{{ route('loans.interest-terms.store', $loan) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label small mb-1" for="term-rate">Zinssatz (% p. a.)</label>
                            <input type="text" inputmode="decimal" id="term-rate" name="rate" class="form-control form-control-sm" placeholder="z. B. 6,5" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1" for="term-from">Gültig ab</label>
                            <input type="date" id="term-from" name="valid_from" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1" for="term-until">Gültig bis (optional)</label>
                            <input type="date" id="term-until" name="valid_until" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Zinssatz erfassen</button>
                        </div>
                        <div class="col-12">
                            <label class="visually-hidden" for="term-note">Notiz</label>
                            <input type="text" id="term-note" name="note" class="form-control form-control-sm" placeholder="Notiz (optional)">
                        </div>
                    </form>
                </div>
            @endif
        </div>

        @if ($isInternal)
            <div class="card">
                <div class="card-header">Risiko und interne Notizen <span class="versal-label ms-2">Nur intern</span></div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="versal-label">Risiko-Einstufung</span><br>
                        @if ($loan->risk_rating)
                            <x-enum-badge :enum="$loan->risk_rating" />
                        @else
                            <span class="text-muted">Keine Angabe</span>
                        @endif
                    </div>
                    <div>
                        <span class="versal-label">Interne Notizen</span>
                        <div class="small mt-1" style="white-space: pre-wrap;">{{ $loan->internal_notes ?: 'Keine internen Notizen.' }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
