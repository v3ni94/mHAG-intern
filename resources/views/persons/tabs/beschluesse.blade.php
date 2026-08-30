{{-- Personenakte: Beschlussbeteiligungen --}}
@php($hasResolutionRoute = \Illuminate\Support\Facades\Route::has('resolutions.show'))

<div class="card">
    <div class="card-header">Beschlüsse mit Beteiligung</div>
    @if (($resolutionParticipations ?? collect())->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-journal-check" message="Keine Beschlussbeteiligungen vorhanden." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Beschlussnummer</th>
                    <th>Titel</th>
                    <th>Gesellschaft</th>
                    <th>Rolle</th>
                    <th>Beschlossen am</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($resolutionParticipations as $participation)
                    @php($resolution = $participation->resolution)
                    @continue(! $resolution)
                    <tr>
                        <td>
                            @if ($hasResolutionRoute)
                                <a href="{{ route('resolutions.show', $resolution) }}" class="text-decoration-none">{{ $resolution->resolution_number }}</a>
                            @else
                                {{ $resolution->resolution_number }}
                            @endif
                        </td>
                        <td>{{ $resolution->title }}</td>
                        <td>{{ $resolution->company?->display_name }}</td>
                        <td>{{ $participation->role }}</td>
                        <td>{{ format_date($resolution->resolved_on) }}</td>
                        <td><x-enum-badge :enum="$resolution->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
