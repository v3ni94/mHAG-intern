@extends('layouts.app')

@section('title', 'Benachrichtigungen')

@section('content')
    <x-page-header title="Benachrichtigungen" label="Organisation">
        @if (auth()->user()->unreadNotifications()->count() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check2-all"></i> Alle als gelesen markieren
                </button>
            </form>
        @endif
    </x-page-header>

    <div class="card">
        @if ($notifications->count() > 0)
            <ul class="list-group list-group-flush">
                @foreach ($notifications as $notification)
                    @php($severity = $notification->data['severity'] ?? 'info')
                    <li class="list-group-item d-flex align-items-start gap-2 {{ $notification->read_at ? '' : 'fw-semibold' }}">
                        <x-status-badge :severity="$severity" :label="match($severity) { 'danger' => 'Kritisch', 'warning' => 'Warnung', 'success' => 'OK', default => 'Info' }" />
                        <div class="flex-grow-1">
                            @if (! empty($notification->data['url']))
                                <a href="{{ $notification->data['url'] }}" class="text-decoration-none text-body">{{ $notification->data['message'] ?? 'Benachrichtigung' }}</a>
                            @else
                                {{ $notification->data['message'] ?? 'Benachrichtigung' }}
                            @endif
                            <div class="text-muted small fw-normal">{{ format_datetime($notification->created_at) }}</div>
                        </div>
                        @unless ($notification->read_at)
                            <span class="badge text-bg-warning">neu</span>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @else
            <div class="card-body">
                <x-empty-state icon="bi-bell-slash" message="Keine Benachrichtigungen vorhanden." />
            </div>
        @endif
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
