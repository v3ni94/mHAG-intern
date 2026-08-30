{{-- Verknüpfte Verträge (Abschnitte 52-56) --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Verträge</span>
        @if (\Illuminate\Support\Facades\Route::has('contracts.create'))
            @can('contracts.create')
                <a href="{{ route('contracts.create', ['loan_id' => $loan->id]) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Vertrag erstellen
                </a>
            @endcan
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Vertragsnummer</th>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Finalisiert am</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->contracts as $contract)
                    <tr>
                        <td class="fw-semibold">{{ $contract->contract_number }}</td>
                        <td>{{ $contract->title }}</td>
                        <td>
                            @switch($contract->status)
                                @case('final') <x-status-badge severity="info" label="Final" /> @break
                                @case('signed') <x-status-badge severity="success" label="Unterschrieben" /> @break
                                @case('cancelled') <x-status-badge severity="danger" label="Storniert" /> @break
                                @default <x-status-badge severity="neutral" label="Entwurf" />
                            @endswitch
                        </td>
                        <td>{{ $contract->finalized_at ? format_datetime($contract->finalized_at) : '' }}</td>
                        <td class="text-end">
                            @if (\Illuminate\Support\Facades\Route::has('contracts.show'))
                                <a href="{{ route('contracts.show', $contract) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-file-earmark-text" message="Keine Verträge verknüpft." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
