{{-- Darlehenskonto (Abschnitt 48): chronologisch, mit laufendem Saldo (Forderungssicht) --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Darlehenskonto (Forderungssicht: + erhöht Forderung, - reduziert)</span>
        <span class="small text-muted">
            Gebucht werden Auszahlungen, Tilgungen, Zins- und Gebührenzahlungen, Zinszuschreibungen,
            Verzugszinsen und Stornos.
            Zins- und Gebühren-Sollstellungen werden im Zahlungsplan geführt.
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Wirkungsdatum</th>
                    <th>Buchungsdatum</th>
                    <th>Buchungsart</th>
                    <th>Beschreibung</th>
                    <th class="text-end">Betrag</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accountRows as $row)
                    @php($transaction = $row['transaction'])
                    <tr class="{{ $transaction->booking_type === \App\Enums\BookingType::Cancellation ? 'table-warning' : '' }}">
                        <td>{{ format_date($transaction->effective_date) }}</td>
                        <td class="text-muted">{{ format_date($transaction->booking_date) }}</td>
                        <td>
                            {{ $transaction->booking_type?->label() }}
                            @if ($transaction->reversal_of)
                                <span class="badge text-bg-light" title="Gegenbuchung zu Buchung Nr. {{ $transaction->reversal_of }}">Storno zu #{{ $transaction->reversal_of }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $transaction->description }}</td>
                        <td class="text-end {{ \App\Support\Money::isNegative($transaction->amount) ? 'text-success' : '' }}">
                            <x-money :amount="$transaction->amount" />
                        </td>
                        <td class="text-end fw-semibold"><x-money :amount="$row['saldo']" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="bi-journal-text" message="Noch keine Kontobuchungen vorhanden." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
