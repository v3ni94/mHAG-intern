{{-- Formularfelder Beschluss (Abschnitt 89) --}}
<div class="col-md-6">
    <label class="form-label required" for="title">Titel</label>
    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
           value="{{ old('title', $resolution->title ?? '') }}" required>
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-3">
    <label class="form-label required" for="type">Beschlussart</label>
    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror"
            @if (isset($resolution) && $resolution->exists) disabled @endif required>
        <option value="">Bitte wählen ...</option>
        @foreach ($types as $type)
            <option value="{{ $type->value }}"
                    @selected(old('type', isset($resolution) ? $resolution->type?->value : ($preselectedType ?? '')) === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </select>
    @if (isset($resolution) && $resolution->exists)
        <input type="hidden" name="type" value="{{ $resolution->type?->value }}">
        <div class="form-text">Beschlussart und Nummer bleiben nach Anlage stabil.</div>
    @else
        <div class="form-text">Die Teilnehmer werden aus dem zuständigen Organ vorbelegt.</div>
    @endif
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-3">
    <label class="form-label required" for="company_entity_id">Gesellschaft</label>
    <select name="company_entity_id" id="company_entity_id" class="form-select @error('company_entity_id') is-invalid @enderror" required>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}"
                    @selected(old('company_entity_id', $resolution->company_entity_id ?? ($defaultCompanyId ?? null)) == $company->id)>
                {{ $company->display_name }}
            </option>
        @endforeach
    </select>
    @error('company_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-3">
    <label class="form-label" for="applicant_entity_id">Antragsteller</label>
    <select name="applicant_entity_id" id="applicant_entity_id" class="form-select @error('applicant_entity_id') is-invalid @enderror">
        <option value="">Keiner</option>
        @foreach ($entities as $entity)
            <option value="{{ $entity->id }}" @selected(old('applicant_entity_id', $resolution->applicant_entity_id ?? null) == $entity->id)>
                {{ $entity->display_name }}
            </option>
        @endforeach
    </select>
    @error('applicant_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-3">
    <label class="form-label" for="resolved_on">Tatsächliches Beschlussdatum</label>
    <input type="date" name="resolved_on" id="resolved_on" class="form-control @error('resolved_on') is-invalid @enderror"
           value="{{ old('resolved_on', isset($resolution) ? $resolution->resolved_on?->format('Y-m-d') : '') }}">
    <div class="form-text">Für historische Beschlüsse; das Erfassungsdatum wird getrennt protokolliert.</div>
    @error('resolved_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-12">
    <label class="form-label" for="motion">Antrag</label>
    <textarea name="motion" id="motion" rows="3" class="form-control @error('motion') is-invalid @enderror">{{ old('motion', $resolution->motion ?? '') }}</textarea>
    @error('motion')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-12">
    <label class="form-label" for="reasoning">Begründung</label>
    <textarea name="reasoning" id="reasoning" rows="3" class="form-control @error('reasoning') is-invalid @enderror">{{ old('reasoning', $resolution->reasoning ?? '') }}</textarea>
    @error('reasoning')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-12">
    <label class="form-label" for="resolution_text">Beschlusstext</label>
    <textarea name="resolution_text" id="resolution_text" rows="4" class="form-control @error('resolution_text') is-invalid @enderror">{{ old('resolution_text', $resolution->resolution_text ?? '') }}</textarea>
    @error('resolution_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Interessenkonflikte (Abschnitt 95): Dokumentation ohne rechtliche Bewertung --}}
<div class="col-12">
    <div class="card">
        <div class="card-header">Interessenkonflikt (optional)</div>
        <div class="card-body">
            <div class="form-check mb-2">
                <input type="hidden" name="conflict_of_interest" value="0">
                <input type="checkbox" name="conflict_of_interest" id="conflict_of_interest" value="1"
                       class="form-check-input" @checked(old('conflict_of_interest', $resolution->conflict_of_interest ?? false))>
                <label class="form-check-label" for="conflict_of_interest">Es liegt ein Interessenkonflikt vor</label>
            </div>
            <label class="form-label small" for="conflict_notes">Betroffene Person, Beschreibung, Teilnahme an Beratung und Abstimmung</label>
            <textarea name="conflict_notes" id="conflict_notes" rows="3"
                      class="form-control @error('conflict_notes') is-invalid @enderror">{{ old('conflict_notes', $resolution->conflict_notes ?? '') }}</textarea>
            <div class="form-text">Reine Dokumentation; das System nimmt keine rechtliche Bewertung vor.</div>
            @error('conflict_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
