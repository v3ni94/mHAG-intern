@php($unread = auth()->user()?->unreadNotifications()->count() ?? 0)
<div class="dropdown">
    <button class="btn btn-outline-secondary btn-sm position-relative" data-bs-toggle="dropdown" aria-label="Benachrichtigungen">
        <i class="bi bi-bell"></i>
        @if ($unread > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px;">
        <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
            <strong class="small">Benachrichtigungen</strong>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="btn btn-link btn-sm p-0 small">Alle als gelesen markieren</button>
                </form>
            @endif
        </div>
        <div style="max-height: 330px; overflow-y: auto;">
            @forelse (auth()->user()?->notifications()->latest()->limit(8)->get() ?? [] as $notification)
                <a href="{{ $notification->data['url'] ?? route('notifications.index') }}" class="dropdown-item small py-2 {{ $notification->read_at ? 'text-muted' : 'fw-semibold' }}" style="white-space: normal;">
                    {{ $notification->data['message'] ?? 'Benachrichtigung' }}
                    {{-- created_at ist nullable. Das Partial liegt im Layout jeder
                         angemeldeten Seite, ein fehlender Zeitstempel haette also
                         die gesamte Anwendung lahmgelegt. --}}
                    <div class="text-muted fw-normal" style="font-size: 0.7rem;">{{ $notification->created_at?->diffForHumans() ?? '' }}</div>
                </a>
            @empty
                <div class="p-3 text-center text-muted small">Keine Benachrichtigungen.</div>
            @endforelse
        </div>
        <div class="p-2 border-top text-center">
            <a href="{{ route('notifications.index') }}" class="small">Alle anzeigen</a>
        </div>
    </div>
</div>
