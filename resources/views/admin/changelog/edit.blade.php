@extends('layouts.app')

@section('title', 'Changelog-Eintrag bearbeiten')

@section('content')
    <x-page-header title="Changelog-Eintrag bearbeiten" label="Administration">
        <a href="{{ route('admin.changelog.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.changelog.update', $entry) }}">
                @csrf
                @method('PUT')
                @include('admin.changelog._form')
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Speichern</button>
                    <a href="{{ route('admin.changelog.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
