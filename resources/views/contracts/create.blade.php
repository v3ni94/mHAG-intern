@extends('layouts.app')

@section('title', 'Vertrag erstellen')

@section('content')
    <x-page-header title="Vertrag erstellen" label="Vertragsverwaltung" />

    <form method="POST" action="{{ route('contracts.store') }}" class="card" style="max-width: 760px;">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="title">Titel <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required
                       placeholder="z. B. Darlehensvertrag DAR-2026-00001">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="contract_template_version_id">Vorlage und Version <span class="text-danger">*</span></label>
                <select name="contract_template_version_id" id="contract_template_version_id"
                        class="form-select @error('contract_template_version_id') is-invalid @enderror" required>
                    <option value="">Bitte wählen ...</option>
                    @foreach ($templates as $template)
                        @foreach ($template->versions as $version)
                            <option value="{{ $version->id }}" @selected((string) old('contract_template_version_id') === (string) $version->id)>
                                {{ $template->name }} · Version {{ $version->version }}@if ($loop->first) (aktuellste)@endif
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('contract_template_version_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Der Vertrag friert die gewählte Vorlagenversion als Snapshot ein. Spätere Vorlagenänderungen wirken sich nicht auf diesen Vertrag aus.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="loan_id">Darlehen (optional)</label>
                <select name="loan_id" id="loan_id" class="form-select @error('loan_id') is-invalid @enderror">
                    <option value="">Kein Darlehen</option>
                    @foreach ($loans as $loan)
                        <option value="{{ $loan->id }}" @selected((string) old('loan_id', $preselectedLoanId) === (string) $loan->id)>
                            {{ $loan->loan_number }} · {{ $loan->title }}
                        </option>
                    @endforeach
                </select>
                @error('loan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Bei Auswahl eines Darlehens werden die Standardplatzhalter automatisch aus den Darlehensdaten befüllt. Fehlende Werte werden warnend angezeigt.</div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> Entwurf erstellen</button>
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
