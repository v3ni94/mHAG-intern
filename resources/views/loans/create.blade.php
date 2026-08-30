@extends('layouts.app')

@section('title', 'Neues Darlehen')

@section('content')
    <x-page-header title="Neues Darlehen" label="Finanzen · Darlehen">
        <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('loans.store') }}">
        @csrf

        @include('loans._form', ['mode' => 'create'])

        <div class="card mb-3">
            <div class="card-header">Auszahlung planen (optional)</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="plan_disbursement" value="0">
                            <input type="checkbox" id="plan_disbursement" name="plan_disbursement" value="1"
                                   class="form-check-input" @checked(old('plan_disbursement'))>
                            <label class="form-check-label" for="plan_disbursement">
                                Auszahlung direkt planen (Abschnitt Auszahlungen der Detailseite)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="disbursement_planned_amount">Geplanter Betrag (EUR)</label>
                        <input type="text" inputmode="decimal" id="disbursement_planned_amount" name="disbursement_planned_amount"
                               class="form-control @error('disbursement_planned_amount') is-invalid @enderror"
                               value="{{ old('disbursement_planned_amount') }}" placeholder="z. B. 100.000,00">
                        @error('disbursement_planned_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="disbursement_planned_date">Geplantes Datum</label>
                        <input type="date" id="disbursement_planned_date" name="disbursement_planned_date"
                               class="form-control @error('disbursement_planned_date') is-invalid @enderror"
                               value="{{ old('disbursement_planned_date') }}">
                        @error('disbursement_planned_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="disbursement_reference">Referenz</label>
                        <input type="text" id="disbursement_reference" name="disbursement_reference"
                               class="form-control @error('disbursement_reference') is-invalid @enderror"
                               value="{{ old('disbursement_reference') }}">
                        @error('disbursement_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Darlehen anlegen</button>
            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
        <p class="text-muted small">
            Die Darlehensnummer wird automatisch vergeben. Das Darlehen startet im Status "Entwurf".
            Bei einem Wirkungsbeginn in der Vergangenheit berechnet das System alle Werte ab diesem Datum automatisch.
        </p>
    </form>
@endsection
