@extends('layouts.app')

@section('title', 'Person bearbeiten')

@section('content')
    <x-page-header :title="$entity->display_name" label="Personenakte bearbeiten">
        <a href="{{ route('persons.show', $entity) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Akte
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('persons.update', $entity) }}">
        @csrf
        @method('PUT')
        @include('persons.partials.form', ['person' => $entity->person, 'entity' => $entity])

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
            <a href="{{ route('persons.show', $entity) }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
