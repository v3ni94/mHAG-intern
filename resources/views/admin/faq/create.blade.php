@extends('layouts.app')

@section('title', 'Neuer FAQ-Eintrag')

@section('content')
    <x-page-header title="Neuer FAQ-Eintrag" label="Administration">
        <a href="{{ route('admin.faq.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.faq.store') }}">
                @csrf
                @include('admin.faq._form')
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Eintrag anlegen</button>
                    <a href="{{ route('admin.faq.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
@endsection
