@extends('layouts.app')

@section('title', 'Was ist neu?')

@section('content')
    <x-page-header title="Was ist neu?" label="Hilfe &amp; Anleitung">
        <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Übersicht</a>
    </x-page-header>

    @forelse ($entries as $entry)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Version {{ $entry->version }}</span>
                <span class="text-muted small">{{ format_date($entry->released_on) }}</span>
            </div>
            <div class="card-body">
                {!! \Illuminate\Support\Str::markdown($entry->changes, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </div>
        </div>
    @empty
        <x-empty-state icon="bi-stars" message="Noch keine Changelog-Einträge vorhanden." />
    @endforelse
@endsection
