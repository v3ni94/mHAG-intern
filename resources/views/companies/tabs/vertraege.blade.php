{{-- Unternehmensakte: Verträge (über die Darlehen des Unternehmens) --}}
@php
    $hasContractRoute = \Illuminate\Support\Facades\Route::has('contracts.show');
    $statusMap = [
        'draft' => ['severity' => 'neutral', 'label' => 'Entwurf'],
        'final' => ['severity' => 'info', 'label' => 'Final'],
        'signed' => ['severity' => 'success', 'label' => 'Unterschrieben'],
        'cancelled' => ['severity' => 'danger', 'label' => 'Storniert'],
    ];
@endphp

<div class="card">
    <div class="card-header">Verträge</div>
    @if (($contracts ?? collect())->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-file-earmark-text" message="Keine Verträge vorhanden." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Vertragsnummer</th>
                    <th>Titel</th>
                    <th>Darlehen</th>
                    <th>Status</th>
                    <th>Finalisiert am</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($contracts as $contract)
                    @php($badge = $statusMap[$contract->status] ?? ['severity' => 'neutral', 'label' => $contract->status])
                    <tr>
                        <td>
                            @if ($hasContractRoute)
                                <a href="{{ route('contracts.show', $contract) }}" class="text-decoration-none">{{ $contract->contract_number }}</a>
                            @else
                                {{ $contract->contract_number }}
                            @endif
                        </td>
                        <td>{{ $contract->title }}</td>
                        <td>{{ $contract->loan?->loan_number }}</td>
                        <td><x-status-badge :severity="$badge['severity']" :label="$badge['label']" /></td>
                        <td>{{ format_datetime($contract->finalized_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
