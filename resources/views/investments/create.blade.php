@extends('layouts.app')
@section('title', 'Beteiligung anlegen')
@section('content')
    <x-page-header title="Beteiligung anlegen" label="Beteiligungen">
        <a href="{{ route('investments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('investments.store') }}" class="row g-3">
                @csrf
                @include('investments._form')
                <div class="col-12">
                    <button class="btn btn-primary">Anlegen</button>
                </div>
            </form>
        </div>
    </div>
@endsection
