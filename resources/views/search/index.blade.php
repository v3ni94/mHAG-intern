@extends('layouts.app')

@section('title', 'Globale Suche')

@section('content')
    <x-page-header title="Globale Suche" label="Suche">
        <x-help-icon text="Suche nach Name, Firma, IBAN, E-Mail, Telefon, Steuernummer, Registernummer, Darlehensnummer, Beschlussnummer, Aktienbewegung und Dokumenten" />
    </x-page-header>

    <form method="GET" action="{{ route('search.index') }}" class="mb-4">
        <div class="input-group" style="max-width: 640px;">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" name="q" value="{{ $q }}" class="form-control"
                   placeholder="Suchbegriff eingeben (mindestens 2 Zeichen)" autofocus>
            <button class="btn btn-primary">Suchen</button>
        </div>
    </form>

    @if ($q === '')
        <x-empty-state icon="bi-search" message="Bitte geben Sie einen Suchbegriff ein." />
    @elseif (mb_strlen($q) < 2)
        <x-empty-state icon="bi-search" message="Der Suchbegriff muss mindestens 2 Zeichen lang sein." />
    @elseif (empty($groups))
        <x-empty-state icon="bi-search" message="Keine Treffer für &bdquo;{{ $q }}&ldquo; gefunden." />
    @else
        <p class="text-muted">{{ $total }} {{ $total === 1 ? 'Treffer' : 'Treffer' }} für &bdquo;{{ $q }}&ldquo;</p>

        <div class="row g-3">
            @foreach ($groups as $group)
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi {{ $group['icon'] }} me-1"></i>
                            {{ $group['label'] }}
                            <span class="badge text-bg-light border ms-1">{{ count($group['items']) }}</span>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($group['items'] as $item)
                                @php($badge = $item['badge'] ?? null)
                                @if (! empty($item['url']))
                                    <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                                        <span>
                                            <span class="fw-semibold">{{ $item['title'] }}</span>
                                            @if (! empty($item['subtitle']))
                                                <span class="text-muted small d-block">{{ $item['subtitle'] }}</span>
                                            @endif
                                        </span>
                                        @if ($badge instanceof \BackedEnum)
                                            <x-enum-badge :enum="$badge" />
                                        @elseif ($badge)
                                            <x-status-badge severity="neutral" :label="$badge" />
                                        @endif
                                    </a>
                                @else
                                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                        <span>
                                            <span class="fw-semibold">{{ $item['title'] }}</span>
                                            @if (! empty($item['subtitle']))
                                                <span class="text-muted small d-block">{{ $item['subtitle'] }}</span>
                                            @endif
                                        </span>
                                        @if ($badge instanceof \BackedEnum)
                                            <x-enum-badge :enum="$badge" />
                                        @elseif ($badge)
                                            <x-status-badge severity="neutral" :label="$badge" />
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
