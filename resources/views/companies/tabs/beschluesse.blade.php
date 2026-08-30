{{-- Unternehmensakte: Beschlüsse der Gesellschaft --}}
@php($hasResolutionRoute = \Illuminate\Support\Facades\Route::has('resolutions.show'))

<div class="card">
    <div class="card-header">Beschlüsse</div>
    @if (($resolutions ?? collect())->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-journal-check" message="Keine Beschlüsse vorhanden." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Beschlussnummer</th>
                    <th>Titel</th>
                    <th>Art</th>
                    <th>Beschlossen am</th>
                    <th>Ergebnis</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($resolutions as $resolution)
                    <tr>
                        <td>
                            @if ($hasResolutionRoute)
                                <a href="{{ route('resolutions.show', $resolution) }}" class="text-decoration-none">{{ $resolution->resolution_number }}</a>
                            @else
                                {{ $resolution->resolution_number }}
                            @endif
                        </td>
                        <td>{{ $resolution->title }}</td>
                        <td>{{ $resolution->type?->label() }}</td>
                        <td>{{ format_date($resolution->resolved_on) }}</td>
                        <td>
                            @if ($resolution->result === 'accepted')
                                <x-status-badge severity="success" icon="bi-check-circle-fill" label="Angenommen" />
                            @elseif ($resolution->result === 'rejected')
                                <x-status-badge severity="danger" icon="bi-x-circle-fill" label="Abgelehnt" />
                            @elseif ($resolution->result === 'postponed')
                                <x-status-badge severity="warning" icon="bi-pause-circle-fill" label="Vertagt" />
                            @elseif ($resolution->result === 'withdrawn')
                                <x-status-badge severity="neutral" icon="bi-dash-circle" label="Zurückgezogen" />
                            @endif
                        </td>
                        <td><x-enum-badge :enum="$resolution->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
