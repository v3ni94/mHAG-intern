@extends('layouts.app')

@section('title', 'Unternehmen bearbeiten')

@section('content')
    <x-page-header :title="$entity->display_name" label="Unternehmensakte bearbeiten">
        <a href="{{ route('companies.show', $entity) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Akte
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('companies.update', $entity) }}">
        @csrf
        @method('PUT')
        @include('companies.partials.form', ['company' => $entity->company, 'entity' => $entity])

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
            <a href="{{ route('companies.show', $entity) }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
