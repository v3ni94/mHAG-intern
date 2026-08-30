@extends('layouts.app')

@section('title', 'Neues Tagesereignis')

@section('content')
    <x-page-header title="Neues Tagesereignis" label="Administration">
        <a href="{{ route('admin.daily-facts.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.daily-facts.store') }}">
                @csrf
                @include('admin.daily-facts._form')
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Eintrag anlegen</button>
                    <a href="{{ route('admin.daily-facts.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
