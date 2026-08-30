@extends('layouts.app')

@section('title', 'Neues Unternehmen')

@section('content')
    <x-page-header title="Neues Unternehmen anlegen" label="Stammdaten">
        <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('companies.store') }}">
        @csrf
        @include('companies.partials.form', ['company' => null, 'entity' => null])
        @include('persons.partials.initial-address', ['addressType' => 'business'])

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Unternehmen anlegen</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
