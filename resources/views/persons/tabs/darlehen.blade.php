{{-- Akte: verknüpfte Darlehen (als Darlehensgeber und Darlehensnehmer) --}}
@php($hasLoanRoute = \Illuminate\Support\Facades\Route::has('loans.show'))

@foreach ([
    ['label' => 'Als Darlehensgeber', 'loans' => $entity->loansAsLender, 'counterLabel' => 'Darlehensnehmer', 'counter' => 'borrower'],
    ['label' => 'Als Darlehensnehmer', 'loans' => $entity->loansAsBorrower, 'counterLabel' => 'Darlehensgeber', 'counter' => 'lender'],
] as $block)
    <div class="card mb-3">
        <div class="card-header">{{ $block['label'] }}</div>
        @if ($block['loans']->isEmpty())
            <div class="card-body">
                <x-empty-state icon="bi-cash-stack" message="Keine Darlehen vorhanden." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Darlehensnummer</th>
                        <th>Bezeichnung</th>
                        <th>{{ $block['counterLabel'] }}</th>
                        <th>Darlehensart</th>
                        <th class="num">Darlehenssumme</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($block['loans'] as $loan)
                        <tr>
                            <td>
                                @if ($hasLoanRoute)
                                    <a href="{{ route('loans.show', $loan) }}" class="fw-semibold text-decoration-none">{{ $loan->loan_number }}</a>
                                @else
                                    <span class="fw-semibold">{{ $loan->loan_number }}</span>
                                @endif
                            </td>
                            <td>{{ $loan->title }}</td>
                            <td>{{ $loan->{$block['counter']}?->display_name }}</td>
                            <td>{{ $loan->loanType?->name }}</td>
                            <td class="num"><x-money :amount="$loan->principal_amount" /></td>
                            <td><x-enum-badge :enum="$loan->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endforeach
