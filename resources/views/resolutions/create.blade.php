@extends('layouts.app')
@section('title', 'Beschluss erfassen')
@section('content')
    <x-page-header title="Beschluss erfassen" label="Beschlüsse">
        <a href="{{ route('resolutions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zum Register
        </a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('resolutions.store') }}" class="row g-3">
                @csrf
                @include('resolutions._form', ['resolution' => null])
                <div class="col-12">
                    <button class="btn btn-primary">Als Entwurf anlegen</button>
                    <span class="text-muted small ms-2">
                        Die Beschlussnummer wird automatisch vergeben (z. B. VOR-{{ now()->year }}-001).
                    </span>
                </div>
            </form>
        </div>
    </div>
@endsection
