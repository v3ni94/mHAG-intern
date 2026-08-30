{{-- Formularfelder Beteiligung (Abschnitt 84); Beträge im deutschen Format --}}
<div class="col-md-4">
    <label class="form-label required" for="company_entity_id">Unternehmen</label>
    <select name="company_entity_id" id="company_entity_id" class="form-select @error('company_entity_id') is-invalid @enderror" required>
        <option value="">Bitte wählen ...</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected(old('company_entity_id', $investment->company_entity_id) == $company->id)>
                {{ $company->display_name }}
            </option>
        @endforeach
    </select>
    @error('company_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-2">
    <label class="form-label" for="share_percentage">Beteiligungsquote (%)</label>
    <input type="text" name="share_percentage" id="share_percentage" inputmode="decimal"
           class="form-control @error('share_percentage') is-invalid @enderror"
           value="{{ old('share_percentage', $investment->share_percentage) }}" placeholder="z. B. 25,1">
    @error('share_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-2">
    <label class="form-label" for="share_count">Anzahl Anteile</label>
    <input type="number" name="share_count" id="share_count" min="0" step="1"
           class="form-control @error('share_count') is-invalid @enderror"
           value="{{ old('share_count', $investment->share_count) }}">
    @error('share_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-2">
    <label class="form-label" for="acquired_on">Anschaffungsdatum</label>
    <input type="date" name="acquired_on" id="acquired_on" class="form-control @error('acquired_on') is-invalid @enderror"
           value="{{ old('acquired_on', $investment->acquired_on?->format('Y-m-d')) }}">
    @error('acquired_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-2">
    <label class="form-label required" for="status">Status</label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        <option value="active" @selected(old('status', $investment->status) === 'active')>Aktiv</option>
        <option value="sold" @selected(old('status', $investment->status) === 'sold')>Verkauft</option>
        <option value="liquidated" @selected(old('status', $investment->status) === 'liquidated')>Liquidiert</option>
    </select>
    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-3">
    <label class="form-label" for="acquisition_cost">Anschaffungskosten (EUR)</label>
    <input type="text" name="acquisition_cost" id="acquisition_cost" inputmode="decimal"
           class="form-control @error('acquisition_cost') is-invalid @enderror"
           value="{{ old('acquisition_cost', $investment->acquisition_cost) }}" placeholder="z. B. 50.000,00">
    @error('acquisition_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-3">
    <label class="form-label" for="current_value">Aktueller interner Wert (EUR)</label>
    <input type="text" name="current_value" id="current_value" inputmode="decimal"
           class="form-control @error('current_value') is-invalid @enderror"
           value="{{ old('current_value', $investment->current_value) }}" placeholder="nur manuelle Bewertung">
    <div class="form-text">Wird ausschließlich manuell gepflegt, nie automatisch ermittelt.</div>
    @error('current_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-12">
    <label class="form-label" for="notes">Notizen</label>
    <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $investment->notes) }}</textarea>
    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
