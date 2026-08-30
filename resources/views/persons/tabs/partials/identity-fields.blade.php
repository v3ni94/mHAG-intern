{{-- Felder eines Identitätsdokuments --}}
@php($doc = $doc ?? null)
<div class="row g-2">
    <div class="col-md-3">
        <label class="form-label">Dokumenttyp *</label>
        <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $doc?->type?->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Dokumentnummer</label>
        <input type="text" name="document_number" value="{{ old('document_number', $doc?->document_number) }}"
               class="form-control form-control-sm @error('document_number') is-invalid @enderror">
        @error('document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Ausstellungsdatum</label>
        <input type="date" name="issued_on" value="{{ old('issued_on', $doc?->issued_on?->format('Y-m-d')) }}"
               class="form-control form-control-sm @error('issued_on') is-invalid @enderror">
        @error('issued_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Ablaufdatum
            <x-help-icon text="Erzeugt automatisch eine Wiedervorlage mit Vorlauf" />
        </label>
        <input type="date" name="expires_on" value="{{ old('expires_on', $doc?->expires_on?->format('Y-m-d')) }}"
               class="form-control form-control-sm @error('expires_on') is-invalid @enderror">
        @error('expires_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Ausstellende Behörde</label>
        <input type="text" name="authority" value="{{ old('authority', $doc?->authority) }}"
               class="form-control form-control-sm @error('authority') is-invalid @enderror">
        @error('authority')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Land</label>
        <input type="text" name="country" value="{{ old('country', $doc?->country ?? 'Deutschland') }}"
               class="form-control form-control-sm @error('country') is-invalid @enderror">
        @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Notiz</label>
        <input type="text" name="note" value="{{ old('note', $doc?->note) }}"
               class="form-control form-control-sm @error('note') is-invalid @enderror">
        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="verified" id="identity-verified-{{ $doc?->id ?? 'new' }}" value="1"
                   class="form-check-input" @checked(old('verified', $doc?->verified))>
            <label class="form-check-label" for="identity-verified-{{ $doc?->id ?? 'new' }}">Geprüft</label>
        </div>
    </div>
</div>
