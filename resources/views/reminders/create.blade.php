@extends('layouts.app')

@section('title', 'Neue Wiedervorlage')

@section('content')
    <x-page-header title="Neue Wiedervorlage" label="Organisation" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('reminders.store') }}">
                @csrf
                @include('reminders._form')
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Wiedervorlage anlegen</button>
                    <a href="{{ route('reminders.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
