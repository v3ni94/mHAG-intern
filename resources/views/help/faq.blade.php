@extends('layouts.app')

@section('title', 'FAQ')

@section('content')
    <x-page-header title="Fragen und Antworten" label="Hilfe &amp; Anleitung">
        <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Übersicht</a>
    </x-page-header>

    @forelse ($groups as $category => $entries)
        <h2 class="h5 mt-4">{{ $category }}</h2>
        <div class="accordion mb-3" id="faq-{{ \Illuminate\Support\Str::slug($category) }}">
            @foreach ($entries as $entry)
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#faq-entry-{{ $entry->id }}" aria-expanded="false" aria-controls="faq-entry-{{ $entry->id }}">
                            {{ $entry->question }}
                        </button>
                    </h3>
                    <div id="faq-entry-{{ $entry->id }}" class="accordion-collapse collapse"
                         data-bs-parent="#faq-{{ \Illuminate\Support\Str::slug($category) }}">
                        <div class="accordion-body">{!! nl2br(e($entry->answer)) !!}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <x-empty-state icon="bi-question-circle" message="Für Ihre Rolle sind derzeit keine FAQ-Einträge hinterlegt." />
    @endforelse
@endsection
