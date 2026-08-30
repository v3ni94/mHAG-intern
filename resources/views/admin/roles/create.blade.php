@extends('layouts.app')

@section('title', 'Neue Rolle')

@section('content')
    <x-page-header title="Neue Rolle" label="Administration">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <form method="POST" action="{{ route('admin.roles.store') }}">
        @csrf
        @include('admin.roles._form')
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary">Rolle anlegen</button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
