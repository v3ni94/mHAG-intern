{{-- Gebühren (Abschnitt 43): CRUD, jede Änderung löst die Neuberechnung aus --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <x-kpi-card label="Gebühren berechnet" :value="format_money($balances['fees_charged'] ?? '0.00')" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Gebühren bezahlt" :value="format_money($balances['fees_paid'] ?? '0.00')" severity="success" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card label="Gebühren offen" :value="format_money($balances['fees_open'] ?? '0.00')"
                    :severity="\App\Support\Money::isPositive($balances['fees_open'] ?? '0.00') ? 'warning' : null" />
    </div>
</div>

<div class="card">
    <div class="card-header">Gebühren</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Art</th>
                    <th>Bezeichnung</th>
                    <th class="text-end">Betrag</th>
                    <th class="text-end">Prozentsatz</th>
                    <th>Wiederkehr</th>
                    <th>Fällig am</th>
                    @if ($canUpdate)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->fees as $fee)
                    <tr>
                        <td>{{ $fee->type?->label() }}</td>
                        <td>{{ $fee->name }}</td>
                        <td class="text-end">@if ($fee->amount !== null)<x-money :amount="$fee->amount" />@endif</td>
                        <td class="text-end">{{ $fee->percentage !== null ? format_percent($fee->percentage) : '' }}</td>
                        <td>
                            @switch($fee->recurrence)
                                @case('monthly') monatlich @break
                                @case('quarterly') quartalsweise @break
                                @case('annual') jährlich @break
                                @default einmalig
                            @endswitch
                        </td>
                        <td>{{ $fee->due_date ? format_date($fee->due_date) : '' }}</td>
                        @if ($canUpdate)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#fee-edit-{{ $fee->id }}" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <x-confirm-form :action="route('loans.fees.destroy', [$loan, $fee])" method="DELETE"
                                                confirm="Gebühr wirklich entfernen? Es wird neu berechnet."
                                                label="" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                            </td>
                        @endif
                    </tr>
                    @if ($canUpdate)
                        <tr class="collapse" id="fee-edit-{{ $fee->id }}">
                            <td colspan="7" class="bg-light">
                                <form method="POST" action="{{ route('loans.fees.update', [$loan, $fee]) }}" class="row g-2 align-items-end p-2">
                                    @csrf
                                    @method('PUT')
                                    @include('loans.tabs._fee-fields', ['fee' => $fee, 'suffix' => '-'.$fee->id])
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $canUpdate ? 7 : 6 }}"><x-empty-state icon="bi-receipt" message="Keine Gebühren erfasst." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canUpdate)
        <div class="card-footer">
            <form method="POST" action="{{ route('loans.fees.store', $loan) }}" class="row g-2 align-items-end">
                @csrf
                @include('loans.tabs._fee-fields', ['fee' => null, 'suffix' => '-neu'])
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Gebühr anlegen</button>
                </div>
            </form>
        </div>
    @endif
</div>
