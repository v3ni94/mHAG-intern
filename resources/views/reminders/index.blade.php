@extends('layouts.app')

@section('title', 'Wiedervorlagen')

@section('content')
    <x-page-header title="Wiedervorlagen" label="Organisation">
        <a href="{{ route('reminders.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Neue Wiedervorlage
        </a>
    </x-page-header>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="open" @selected(request('status', 'open') === 'open')>Offen</option>
                <option value="done" @selected(request('status') === 'done')>Erledigt</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Abgebrochen</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="assigned_to" class="form-select form-select-sm" aria-label="Zugewiesen an">
                <option value="">Alle Benutzer</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(request('assigned_to') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="priority" class="form-select form-select-sm" aria-label="Priorität">
                <option value="">Alle Prioritäten</option>
                <option value="high" @selected(request('priority') === 'high')>Hoch</option>
                <option value="normal" @selected(request('priority') === 'normal')>Normal</option>
                <option value="low" @selected(request('priority') === 'low')>Niedrig</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button class="btn btn-sm btn-outline-secondary">Filtern</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fällig</th>
                        <th>Titel</th>
                        <th>Bezug</th>
                        <th>Zugewiesen an</th>
                        <th>Priorität</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reminders as $reminder)
                        @php($overdue = $reminder->status === \App\Enums\ReminderStatus::Open && $reminder->due_date->isPast() && ! $reminder->due_date->isToday())
                        <tr class="{{ $overdue ? 'table-danger' : '' }}">
                            <td class="text-nowrap">
                                {{ format_date($reminder->due_date) }}
                                @if ($reminder->due_time)
                                    <span class="text-muted small">{{ substr($reminder->due_time, 0, 5) }} Uhr</span>
                                @endif
                                @if ($overdue)
                                    <x-status-badge severity="danger" label="Überfällig" />
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $reminder->title }}</div>
                                @if ($reminder->description)
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($reminder->description, 90) }}</div>
                                @endif
                            </td>
                            <td class="small">
                                @if ($reminder->remindable)
                                    {{ class_basename($reminder->remindable_type) }}:
                                    {{ $reminder->remindable->display_name
                                        ?? $reminder->remindable->loan_number
                                        ?? $reminder->remindable->title
                                        ?? ('#'.$reminder->remindable_id) }}
                                @else
                                    <span class="text-muted">ohne Bezug</span>
                                @endif
                            </td>
                            <td>{{ $reminder->assignee?->name ?? 'nicht zugewiesen' }}</td>
                            <td><x-enum-badge :enum="$reminder->priority" /></td>
                            <td><x-enum-badge :enum="$reminder->status" /></td>
                            <td class="text-end text-nowrap">
                                @if ($reminder->status === \App\Enums\ReminderStatus::Open)
                                    <a href="{{ route('reminders.edit', $reminder) }}" class="btn btn-sm btn-outline-secondary" aria-label="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <x-confirm-form :action="route('reminders.done', $reminder)" confirm="Wiedervorlage als erledigt markieren?"
                                                    label="Erledigt" icon="bi-check-lg" class="btn btn-sm btn-outline-success" />
                                    <x-confirm-form :action="route('reminders.cancel', $reminder)" confirm="Wiedervorlage abbrechen?"
                                                    label="Abbrechen" icon="bi-x-lg" class="btn btn-sm btn-outline-danger" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><x-empty-state icon="bi-bell" message="Keine Wiedervorlagen gefunden." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $reminders->links() }}</div>
@endsection
