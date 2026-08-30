@extends('layouts.app')

@section('title', 'Kalender')

@section('content')
    <x-page-header title="Kalender" label="Organisation">
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['month' => $previousMonth]) }}" aria-label="Vorheriger Monat">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index') }}">Heute</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['month' => $nextMonth]) }}" aria-label="Nächster Monat">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-header">{{ $month->locale('de')->isoFormat('MMMM YYYY') }}</div>
        <div class="card-body p-0" style="overflow-x: auto;">
            <table class="table table-bordered mb-0" style="table-layout: fixed; min-width: 840px;">
                <thead>
                    <tr>
                        @foreach (['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $weekday)
                            <th class="text-center small">{{ $weekday }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td class="align-top p-1 {{ $day['inMonth'] ? '' : 'bg-light text-muted' }}" style="height: 108px;">
                                    <a href="{{ route('calendar.index', ['month' => $month->format('Y-m'), 'day' => $day['date']->toDateString()]) }}"
                                       class="d-inline-block text-decoration-none small mb-1 px-1 rounded-1 {{ $day['isToday'] ? 'text-white' : 'text-body' }}"
                                       @if ($day['isToday']) style="background: #2E2D2E;" @endif>
                                        {{ $day['date']->day }}
                                    </a>
                                    @foreach (array_slice($day['events'], 0, 3) as $event)
                                        <div class="text-truncate">
                                            <span class="badge text-bg-{{ $event['severity'] === 'danger' ? 'danger' : ($event['severity'] === 'warning' ? 'warning' : 'secondary') }}"
                                                  style="font-size: 0.62rem;">{{ $event['type'] }}</span>
                                            <span class="small">{{ \Illuminate\Support\Str::limit($event['label'], 28) }}</span>
                                        </div>
                                    @endforeach
                                    @if (count($day['events']) > 3)
                                        <a class="small text-muted"
                                           href="{{ route('calendar.index', ['month' => $month->format('Y-m'), 'day' => $day['date']->toDateString()]) }}">
                                            + {{ count($day['events']) - 3 }} weitere
                                        </a>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($selectedDay)
        <div class="card mb-4">
            <div class="card-header">Termine am {{ format_date($selectedDay) }}</div>
            @if (count($dayEvents) > 0)
                <ul class="list-group list-group-flush">
                    @foreach ($dayEvents as $event)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <x-status-badge :severity="$event['severity']" :label="$event['type']" />
                            @if ($event['url'])
                                <a href="{{ $event['url'] }}" class="text-decoration-none">{{ $event['label'] }}</a>
                            @else
                                <span>{{ $event['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="card-body">
                    <x-empty-state icon="bi-calendar3" message="Keine Termine an diesem Tag." />
                </div>
            @endif
        </div>
    @endif

    <p class="text-muted small">
        Quellen: Zahlungsplan (Zinsen, Tilgungen, Gebühren), Vertragsende und Endfälligkeit, Sicherheiten und Bürgschaften,
        Identitätsdokumente, Organmandate, Beschlüsse und Wiedervorlagen.
    </p>
@endsection
