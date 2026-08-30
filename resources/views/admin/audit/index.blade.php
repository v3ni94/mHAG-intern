@extends('layouts.app')

@section('title', 'Audit-Log')

@section('content')
    <x-page-header title="Audit-Trail" label="Administration" />

    <form method="GET" class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <select name="user_id" class="form-select form-select-sm" aria-label="Benutzer">
                <option value="">Alle Benutzer</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="action" class="form-select form-select-sm" aria-label="Aktion">
                <option value="">Alle Aktionen</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="auditable_type" class="form-select form-select-sm" aria-label="Objekttyp">
                <option value="">Alle Objekttypen</option>
                @foreach ($objectTypes as $type)
                    <option value="{{ $type }}" @selected(request('auditable_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <input type="date" name="von" value="{{ request('von') }}" class="form-control form-control-sm" aria-label="Von">
        </div>
        <div class="col-6 col-md-2">
            <input type="date" name="bis" value="{{ request('bis') }}" class="form-control form-control-sm" aria-label="Bis">
        </div>
        <div class="col-6 col-md-12">
            <button class="btn btn-sm btn-outline-secondary">Filtern</button>
            <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-link">Zurücksetzen</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>Benutzer</th>
                        <th>Aktion</th>
                        <th>Objekt</th>
                        <th>IP-Adresse</th>
                        <th class="text-end">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap small">{{ format_datetime($log->created_at) }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td><code class="small">{{ $log->action }}</code></td>
                            <td class="small">
                                @if ($log->auditable_type)
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                @else
                                    <span class="text-muted">–</span>
                                @endif
                            </td>
                            <td class="small">{{ $log->ip }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.audit.show', $log) }}" class="btn btn-sm btn-outline-secondary" aria-label="Details anzeigen">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-list-check" message="Keine Audit-Einträge für die gewählten Filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
