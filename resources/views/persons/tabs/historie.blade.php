{{-- Akte: Historie (Audit-Trail zur Entity und ihren Unterressourcen) --}}
<div class="card">
    <div class="card-header">Historie / Audit-Trail</div>
    @if (($auditLogs ?? collect())->count() === 0)
        <div class="card-body">
            <x-empty-state icon="bi-clock-history" message="Keine Einträge vorhanden." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Benutzer</th>
                    <th>Aktion</th>
                    <th>Änderungen</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($auditLogs as $log)
                    <tr>
                        <td class="text-nowrap">{{ format_datetime($log->created_at) }}</td>
                        <td>{{ $log->user?->name ?: 'System' }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td class="small">
                            @if ($log->new_values)
                                @foreach (collect($log->new_values)->take(6) as $field => $value)
                                    <div>
                                        <span class="text-muted">{{ $field }}:</span>
                                        {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : \Illuminate\Support\Str::limit((string) $value, 80) }}
                                    </div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body pt-2 pb-2">
            {{ $auditLogs->links() }}
        </div>
    @endif
</div>
