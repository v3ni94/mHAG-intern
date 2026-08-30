{{-- Neuberechnungsprotokoll (Abschnitt 38) + manueller Anstoß (Abschnitt 36) --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Neuberechnungen</span>
        @if ($canUpdate)
            <x-confirm-form :action="route('loans.recalculate', $loan)" method="POST"
                            confirm="Neuberechnung jetzt ausführen? Alle abgeleiteten Werte werden ab Wirkungsbeginn neu berechnet."
                            label="Neuberechnung ausführen" icon="bi-arrow-clockwise" class="btn btn-sm btn-primary" />
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Auslöser</th>
                    <th>Benutzer</th>
                    <th>Frühestes Datum</th>
                    <th>Status</th>
                    <th class="text-end">Dauer</th>
                    <th>Fehler</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->recalculations as $recalculation)
                    <tr>
                        <td class="small">{{ format_datetime($recalculation->created_at) }}</td>
                        <td><code class="small">{{ $recalculation->trigger_action }}</code></td>
                        <td class="small">{{ $recalculation->triggeredBy?->name ?: 'System' }}</td>
                        <td>{{ $recalculation->earliest_affected_date ? format_date($recalculation->earliest_affected_date) : '' }}</td>
                        <td>
                            @if ($recalculation->status === 'ok')
                                <x-status-badge severity="success" label="OK" />
                            @else
                                <x-status-badge severity="danger" label="Fehler" />
                            @endif
                        </td>
                        <td class="text-end small">{{ $recalculation->duration_ms !== null ? $recalculation->duration_ms.' ms' : '' }}</td>
                        <td class="small text-danger">{{ $recalculation->error }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="bi-arrow-clockwise" message="Noch keine Neuberechnungen protokolliert." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Jede Neuberechnung wird mit altem und neuem Stand protokolliert. Gleiche Eingangsdaten liefern stets dasselbe Ergebnis.
    </div>
</div>
