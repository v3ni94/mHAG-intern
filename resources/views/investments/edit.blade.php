@extends('layouts.app')
@section('title', 'Beteiligung bearbeiten')
@section('content')
    <x-page-header :title="'Beteiligung bearbeiten: '.($investment->company?->display_name ?? '')" label="Beteiligungen">
        <a href="{{ route('investments.show', $investment) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Akte
        </a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('investments.update', $investment) }}" class="row g-3">
                @csrf @method('PUT')
                @include('investments._form')
                <div class="col-12">
                    <button class="btn btn-primary">Speichern</button>
                    <span class="text-muted small ms-2">Historische Veränderungen bleiben über den Audit-Trail nachvollziehbar.</span>
                </div>
            </form>
        </div>
    </div>
@endsection
