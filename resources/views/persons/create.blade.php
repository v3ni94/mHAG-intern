@extends('layouts.app')

@section('title', 'Neue Person')

@section('content')
    <x-page-header title="Neue Person anlegen" label="Stammdaten">
        <a href="{{ route('persons.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('persons.store') }}">
        @csrf
        @include('persons.partials.form', ['person' => null, 'entity' => null])
        @include('persons.partials.initial-address', ['addressType' => 'main'])

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Person anlegen</button>
            <a href="{{ route('persons.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
