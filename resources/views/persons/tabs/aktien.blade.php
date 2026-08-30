{{-- Akte: Aktionärsstellung und Aktienbewegungen --}}
@php
    $shareholder = $entity->shareholder;
    $hasTxRoute = \Illuminate\Support\Facades\Route::has('share-transactions.show');
    $transactions = $shareholder
        ? $shareholder->purchases->concat($shareholder->sales)->unique('id')->sortByDesc(fn ($t) => $t->economic_transfer_date?->timestamp ?? 0)
        : collect();
@endphp

@if (! $shareholder)
    <div class="card">
        <div class="card-body">
            <x-empty-state icon="bi-graph-up-arrow" message="Keine Aktionärsstellung vorhanden." />
        </div>
    </div>
@else
    <div class="card mb-3">
        <div class="card-header">Aktionärsstellung</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Aktionärsnummer</dt>
                <dd class="col-sm-9">{{ $shareholder->shareholder_number }}</dd>
                <dt class="col-sm-3">Eintritt</dt>
                <dd class="col-sm-9">{{ format_date($shareholder->joined_on) ?: 'Nicht erfasst' }}</dd>
                <dt class="col-sm-3">Austritt</dt>
                <dd class="col-sm-9">{{ format_date($shareholder->left_on) ?: 'Nicht ausgeschieden' }}</dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if ($shareholder->status === 'active')
                        <x-status-badge severity="success" icon="bi-check-circle-fill" label="Aktiv" />
                    @else
                        <x-status-badge severity="neutral" icon="bi-dash-circle" label="Inaktiv" />
                    @endif
                </dd>
            </dl>
            <div class="text-muted small mt-2">
                Der Aktienbestand wird immer aus den wirksamen Aktienbewegungen berechnet und im Holding-Modul ausgewiesen.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Aktienbewegungen</div>
        @if ($transactions->isEmpty())
            <div class="card-body">
                <x-empty-state icon="bi-arrow-repeat" message="Keine Aktienbewegungen vorhanden." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Nummer</th>
                        <th>Art</th>
                        <th>Veräußerer</th>
                        <th>Erwerber</th>
                        <th class="num">Stück</th>
                        <th>Wirtschaftlicher Übergang</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($transactions as $tx)
                        <tr>
                            <td>
                                @if ($hasTxRoute)
                                    <a href="{{ route('share-transactions.show', $tx) }}" class="text-decoration-none">{{ $tx->transaction_number }}</a>
                                @else
                                    {{ $tx->transaction_number }}
                                @endif
                            </td>
                            <td><x-enum-badge :enum="$tx->type" /></td>
                            <td>{{ $tx->seller?->entity?->display_name }}</td>
                            <td>{{ $tx->buyer?->entity?->display_name }}</td>
                            <td class="num">{{ number_format((int) $tx->share_count, 0, ',', '.') }}</td>
                            <td>{{ format_date($tx->economic_transfer_date) }}</td>
                            <td><x-enum-badge :enum="$tx->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
