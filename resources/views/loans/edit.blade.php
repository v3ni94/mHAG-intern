@extends('layouts.app')

@section('title', 'Darlehen bearbeiten')

@section('content')
    <x-page-header :title="'Darlehen '.$loan->loan_number.' bearbeiten'" label="Finanzen · Darlehen">
        <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Detailseite
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('loans.update', $loan) }}">
        @csrf
        @method('PUT')

        @include('loans._form', ['mode' => 'edit'])

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Änderungen speichern</button>
            <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
        <p class="text-muted small">
            Zinssätze werden über die historisierte Staffel auf der Detailseite (Reiter Übersicht) gepflegt.
            Finanzrelevante Änderungen lösen automatisch eine Neuberechnung aus.
        </p>
    </form>
@endsection
