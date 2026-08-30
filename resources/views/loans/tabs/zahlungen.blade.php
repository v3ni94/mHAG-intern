{{-- Zahlungen dieses Darlehens (Abschnitte 46-49) --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Zahlungen</span>
        @if ($canRecord)
            <a href="{{ route('payments.create', ['loan_id' => $loan->id]) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Zahlung erfassen
            </a>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Valuta</th>
                    <th>Zahler</th>
                    <th>Empfänger</th>
                    <th class="text-end">Betrag</th>
                    <th>Richtung</th>
                    <th>Herkunft</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->payments as $payment)
                    <tr class="{{ $payment->status === 'cancelled' ? 'text-muted' : '' }}">
                        <td>{{ format_date($payment->payment_date) }}</td>
                        <td>{{ $payment->value_date ? format_date($payment->value_date) : '' }}</td>
                        <td>{{ $payment->payer?->display_name }}</td>
                        <td>{{ $payment->payee?->display_name }}</td>
                        <td class="text-end"><x-money :amount="$payment->amount" /></td>
                        <td>{{ $payment->direction === 'incoming' ? 'Eingang' : 'Ausgang' }}</td>
                        <td><x-origin-badge :origin="$payment->origin" /></td>
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
                    <tr><td colspan="9"><x-empty-state icon="bi-arrow-left-right" message="Noch keine Zahlungen erfasst." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Zahlungen werden nie gelöscht. Ein Storno ist nur mit Grund möglich und erzeugt Gegenbuchungen im Darlehenskonto.
    </div>
</div>
