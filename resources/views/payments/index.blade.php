@extends('layouts.app')

@section('title', 'Zahlungen')

@section('content')
    <x-page-header title="Zahlungen" label="Finanzen">
        @if ($canRecord)
            <a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Zahlung erfassen
            </a>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('payments.index') }}" class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="filter-loan">Darlehen</label>
                    <select id="filter-loan" name="loan_id" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}" @selected((string) $filters['loan_id'] === (string) $loan->id)>{{ $loan->loan_number }} {{ $loan->title ? '· '.$loan->title : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-from">Von</label>
                    <input type="date" id="filter-from" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-to">Bis</label>
                    <input type="date" id="filter-to" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-origin">Herkunft</label>
                    <select id="filter-origin" name="origin" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($origins as $origin)
                            <option value="{{ $origin->value }}" @selected($filters['origin'] === $origin->value)>{{ $origin->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        <option value="recorded" @selected($filters['status'] === 'recorded')>Erfasst</option>
                        <option value="cancelled" @selected($filters['status'] === 'cancelled')>Storniert</option>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filtern</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Darlehen</th>
                        <th>Zahler</th>
                        <th>Empfänger</th>
                        <th class="text-end">Betrag</th>
                        <th>Richtung</th>
                        <th>Herkunft</th>
                        <th>Konten</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr class="{{ $payment->status === 'cancelled' ? 'text-muted' : '' }}">
                            <td>{{ format_date($payment->payment_date) }}</td>
                            <td>
                                <a href="{{ route('loans.show', ['loan' => $payment->loan_id, 'tab' => 'zahlungen']) }}">{{ $payment->loan?->loan_number }}</a>
                            </td>
                            <td>{{ $payment->payer?->display_name }}</td>
                            <td>{{ $payment->payee?->display_name }}</td>
                            <td class="text-end"><x-money :amount="$payment->amount" /></td>
                            <td>{{ $payment->direction === 'incoming' ? 'Eingang' : 'Ausgang' }}</td>
                            <td><x-origin-badge :origin="$payment->origin" /></td>
                            {{-- Konten kompakt (Abschnitt 46); IBAN nur fuer Berechtigte --}}
                            <td class="small">
                                @php
                                    $von = $payment->payerBankAccount ?: $payment->bankAccount;
                                    $auf = $payment->payeeBankAccount;
                                    $sichtbar = fn ($konto) => $konto && ($canSeeAccounts
                                        || in_array((int) $konto->entity_id, array_map('intval', $visibleEntityIds), true));
                                @endphp
                                <span class="text-muted">von</span>
                                @if ($sichtbar($von))
                                    <span class="font-monospace">{{ \App\Http\Controllers\PaymentController::formatIban($von->iban) }}</span>
                                @else
                                    <span class="text-muted">{{ $von ? 'nicht sichtbar' : 'ohne' }}</span>
                                @endif
                                <br>
                                <span class="text-muted">auf</span>
                                @if ($sichtbar($auf))
                                    <span class="font-monospace">{{ \App\Http\Controllers\PaymentController::formatIban($auf->iban) }}</span>
                                @else
                                    <span class="text-muted">{{ $auf ? 'nicht sichtbar' : 'ohne' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($payment->status === 'cancelled')
                                    <x-status-badge severity="danger" icon="bi-x-octagon" label="Storniert" />
                                @else
                                    <x-status-badge severity="success" icon="bi-check-circle" label="Erfasst" />
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('payments.show', $payment) }}" class="btn btn-sm btn-outline-secondary" title="Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty-state icon="bi-arrow-left-right" message="Keine Zahlungen gefunden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection
