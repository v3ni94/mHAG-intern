@extends('layouts.app')

@section('title', 'Eintrag Wussten Sie? bearbeiten')

@section('content')
    <x-page-header title='Eintrag "Wussten Sie?" bearbeiten' label="Administration">
        <a href="{{ route('admin.daily-facts.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.daily-facts.update', $entry) }}">
                @csrf
                @method('PUT')
                @include('admin.daily-facts._form')
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Speichern</button>
                    <a href="{{ route('admin.daily-facts.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
