@extends('layouts.app')

@section('title', 'Zahlung vom '.format_date($payment->payment_date))

@section('content')
    <x-page-header :title="'Zahlung über '.format_money($payment->amount)" label="Finanzen · Zahlungen">
        @if ($payment->status === 'cancelled')
            <x-status-badge severity="danger" icon="bi-x-octagon" label="Storniert" />
        @else
            <x-status-badge severity="success" icon="bi-check-circle" label="Erfasst" />
        @endif
        <a href="{{ route('loans.show', ['loan' => $payment->loan_id, 'tab' => 'zahlungen']) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-cash-stack"></i> Zum Darlehen
        </a>
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Zahlungsdaten</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4">Darlehen</dt>
                        <dd class="col-8">{{ $payment->loan?->loan_number }} {{ $payment->loan?->title ? '· '.$payment->loan->title : '' }}</dd>
                        <dt class="col-4">Zahler</dt><dd class="col-8">{{ $payment->payer?->display_name ?: 'ohne Angabe' }}</dd>
                        <dt class="col-4">Empfänger</dt><dd class="col-8">{{ $payment->payee?->display_name ?: 'ohne Angabe' }}</dd>
                        <dt class="col-4">Zahlungsdatum</dt><dd class="col-8">{{ format_date($payment->payment_date) }}</dd>
                        <dt class="col-4">Valutadatum</dt><dd class="col-8">{{ $payment->value_date ? format_date($payment->value_date) : 'ohne' }}</dd>
                        <dt class="col-4">Betrag</dt><dd class="col-8"><x-money :amount="$payment->amount" /></dd>
                        <dt class="col-4">Richtung</dt><dd class="col-8">{{ $payment->direction === 'incoming' ? 'Eingang' : 'Ausgang' }}</dd>
                        <dt class="col-4">Herkunft</dt><dd class="col-8"><x-origin-badge :origin="$payment->origin" /></dd>
                        <dt class="col-4">Verwendungszweck</dt><dd class="col-8">{{ $payment->purpose ?: 'ohne' }}</dd>
                        <dt class="col-4">Referenz</dt><dd class="col-8">{{ $payment->reference ?: 'ohne' }}</dd>
                        <dt class="col-4">Notiz</dt><dd class="col-8">{{ $payment->note ?: 'ohne' }}</dd>
                        <dt class="col-4">Erfasst am</dt><dd class="col-8">{{ format_datetime($payment->created_at) }}</dd>
                    </dl>
                    @if ($payment->status === 'cancelled')
                        <div class="alert alert-danger mt-3 mb-0 py-2 small">
                            <strong>Storniert</strong> am {{ format_datetime($payment->cancelled_at) }}.<br>
                            Grund: {{ $payment->cancel_reason }}
                        </div>
                    @endif
                </div>
                @if ($canCancel && $payment->status !== 'cancelled')
                    <div class="card-footer">
                        <form method="POST" action="{{ route('payments.cancel', $payment) }}" class="row g-2 align-items-end"
                              onsubmit="return confirm('Zahlung wirklich stornieren? Es werden Gegenbuchungen erstellt.');">
                            @csrf
                            <div class="col-md-8">
                                <label class="form-label small mb-1" for="cancel_reason">Stornogrund (Pflicht)</label>
                                <input type="text" id="cancel_reason" name="cancel_reason"
                                       class="form-control form-control-sm @error('cancel_reason') is-invalid @enderror" required>
                                @error('cancel_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 d-grid">
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-octagon"></i> Stornieren</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-6 d-flex flex-column gap-3">
            <div class="card">
                <div class="card-header">Verrechnung</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th class="text-end">Betrag</th>
                                <th>Zahlungsplan-Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payment->allocations as $allocation)
                                <tr>
                                    <td>{{ \App\Enums\AllocationBucket::tryFrom($allocation->bucket)?->label() ?? $allocation->bucket }}</td>
                                    <td class="text-end"><x-money :amount="$allocation->amount" /></td>
                                    <td class="small text-muted">
                                        @if ($allocation->repaymentPlanItem)
                                            {{ $allocation->repaymentPlanItem->item_type?->label() }} {{ format_date($allocation->repaymentPlanItem->due_date) }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><x-empty-state icon="bi-diagram-2" message="Keine Verrechnung vorhanden." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Kontobuchungen aus dieser Zahlung</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Wirkungsdatum</th>
                                <th>Buchungsart</th>
                                <th class="text-end">Betrag</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                <tr>
                                    <td>{{ format_date($transaction->effective_date) }}</td>
                                    <td>
                                        {{ $transaction->booking_type?->label() }}
                                        @if ($transaction->reversal_of)
                                            <span class="badge text-bg-light">Storno zu #{{ $transaction->reversal_of }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end"><x-money :amount="$transaction->amount" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><x-empty-state icon="bi-journal-text" message="Keine Kontobuchungen vorhanden." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
