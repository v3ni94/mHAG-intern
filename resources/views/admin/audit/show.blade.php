@extends('layouts.app')

@section('title', 'Audit-Eintrag')

@section('content')
    <x-page-header :title="'Audit-Eintrag #'.$log->id" label="Administration">
        <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-3">Zeitpunkt</dt><dd class="col-9">{{ format_datetime($log->created_at) }}</dd>
                <dt class="col-3">Benutzer</dt><dd class="col-9">{{ $log->user?->name ?? 'System' }}</dd>
                <dt class="col-3">Aktion</dt><dd class="col-9"><code>{{ $log->action }}</code></dd>
                <dt class="col-3">Objekt</dt>
                <dd class="col-9">
                    @if ($log->auditable_type)
                        {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                    @else
                        –
                    @endif
                </dd>
                <dt class="col-3">IP-Adresse</dt><dd class="col-9">{{ $log->ip ?? '–' }}</dd>
                <dt class="col-3">User-Agent</dt><dd class="col-9 text-break">{{ $log->user_agent ?? '–' }}</dd>
            </dl>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Vorherige Werte</div>
                <div class="card-body">
                    @if ($log->old_values)
                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <span class="text-muted small">Keine vorherigen Werte erfasst.</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Neue Werte</div>
                <div class="card-body">
                    @if ($log->new_values)
                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <span class="text-muted small">Keine neuen Werte erfasst.</span>
                    @endif
                </div>
            </div>
        </div>
        @if ($log->context)
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Kontext</div>
                    <div class="card-body">
                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
