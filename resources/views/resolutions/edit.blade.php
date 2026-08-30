@extends('layouts.app')
@section('title', 'Beschluss bearbeiten')
@section('content')
    <x-page-header :title="'Beschluss bearbeiten: '.$resolution->resolution_number" label="Beschlüsse">
        <a href="{{ route('resolutions.show', $resolution) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zum Beschluss
        </a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('resolutions.update', $resolution) }}" class="row g-3">
                @csrf @method('PUT')
                @include('resolutions._form')
                <div class="col-12">
                    <button class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
@endsection
