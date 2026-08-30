@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" label="Hilfe &amp; Anleitung">
        <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Übersicht</a>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body help-content">
                    @include('help.pages.'.$slug)
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">Weitere Anleitungen</div>
                <div class="list-group list-group-flush">
                    @foreach ($pages as $otherSlug => $otherTitle)
                        <a href="{{ route('help.page', $otherSlug) }}"
                           class="list-group-item list-group-item-action small {{ $otherSlug === $slug ? 'fw-bold' : '' }}">
                            {{ $otherTitle }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
