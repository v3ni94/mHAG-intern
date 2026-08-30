@extends('layouts.app')

@section('title', 'Wiedervorlage bearbeiten')

@section('content')
    <x-page-header title="Wiedervorlage bearbeiten" label="Organisation" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('reminders.update', $reminder) }}">
                @csrf
                @method('PUT')
                @include('reminders._form')
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Speichern</button>
                    <a href="{{ route('reminders.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
