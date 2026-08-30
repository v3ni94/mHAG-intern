@extends('layouts.app')

@section('title', 'Hilfe-Suche')

@section('content')
    <x-page-header title="Hilfe-Suche" label="Hilfe &amp; Anleitung">
        <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Übersicht</a>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        <div class="input-group" style="max-width: 480px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" name="q" value="{{ $term }}" class="form-control" placeholder="Suchbegriff" aria-label="Suchbegriff" autofocus>
            <button class="btn btn-outline-secondary">Suchen</button>
        </div>
    </form>

    @if ($term !== '')
        <p class="small text-muted">
            @php($total = $pageResults->count() + $faqResults->count())
            {{ $total }} {{ $total === 1 ? 'Treffer' : 'Treffer' }} für "{{ $term }}"
            @if (! empty($terms))
                (gesucht wurde nach den Begriffen: {{ implode(', ', $terms) }})
            @endif
        </p>

        <h2 class="h6 text-uppercase text-muted">Anleitungen</h2>
        @if ($pageResults->isNotEmpty())
            <div class="list-group mb-4">
                @foreach ($pageResults as $result)
                    <a href="{{ route('help.page', $result['slug']) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-journal-text text-secondary"></i>
                            <span class="fw-semibold">{{ $result['title'] }}</span>
                        </div>
                        @if ($result['excerpt'] !== '')
                            <div class="small text-muted mt-1">{{ $result['excerpt'] }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-muted small mb-4">Keine passenden Anleitungen gefunden.</p>
        @endif

        <h2 class="h6 text-uppercase text-muted">FAQ</h2>
        @if ($faqResults->isNotEmpty())
            <div class="accordion mb-4" id="faq-results">
                @foreach ($faqResults as $result)
                    @php($entry = $result['entry'])
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#result-{{ $entry->id }}" aria-expanded="false" aria-controls="result-{{ $entry->id }}">
                                {{ $entry->question }}
                            </button>
                        </h3>
                        <div id="result-{{ $entry->id }}" class="accordion-collapse collapse" data-bs-parent="#faq-results">
                            <div class="accordion-body">{!! nl2br(e($entry->answer)) !!}</div>
                        </div>
                    </div>
                    @if ($result['excerpt'] !== '')
                        <div class="small text-muted px-3 py-1 border-start border-end">{{ $result['excerpt'] }}</div>
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-muted small">Keine passenden FAQ-Einträge gefunden.</p>
        @endif
    @else
        <p class="text-muted">Bitte geben Sie einen Suchbegriff mit mindestens drei Zeichen ein, zum Beispiel "Zinsen nicht bezahlt".</p>
    @endif
@endsection
