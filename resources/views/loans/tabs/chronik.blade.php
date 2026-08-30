{{-- Chronik: Statushistorie und Audit-Trail des Darlehens (Abschnitte 21, 120) --}}
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Statushistorie</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Zeitpunkt</th>
                            <th>Von</th>
                            <th>Nach</th>
                            <th>Durch</th>
                            <th>Notiz</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($loan->statusHistory->sortByDesc('created_at') as $entry)
                            <tr>
                                <td class="small">
                                    {{ format_datetime($entry->created_at) }}
                                    @if ($entry->effective_date)
                                        <div class="text-muted">wirksam {{ format_date($entry->effective_date) }}</div>
                                    @endif
                                </td>
                                <td>{{ $entry->from_status ? \App\Enums\LoanStatus::from($entry->from_status)->label() : '' }}</td>
                                <td><x-enum-badge :enum="\App\Enums\LoanStatus::from($entry->to_status)" /></td>
                                <td class="small">{{ $entry->changedBy?->name }}</td>
                                <td class="small text-muted">{{ $entry->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="bi-clock-history" message="Noch keine Statuswechsel." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Audit-Trail</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Zeitpunkt</th>
                            <th>Aktion</th>
                            <th>Benutzer</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $log)
                            <tr>
                                <td class="small">{{ format_datetime($log->created_at) }}</td>
                                <td><code class="small">{{ $log->action }}</code></td>
                                <td class="small">{{ $log->user?->name ?: 'System' }}</td>
                                <td class="small text-muted">
                                    @if ($log->new_values)
                                        {{ \Illuminate\Support\Str::limit(collect($log->new_values)->map(fn ($v, $k) => $k.': '.(is_scalar($v) ? $v : json_encode($v)))->implode(', '), 120) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-list-check" message="Keine Audit-Einträge vorhanden." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
