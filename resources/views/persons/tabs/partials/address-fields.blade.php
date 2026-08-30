{{-- Adressfelder (Neuanlage und Bearbeitung) --}}
@php($address = $address ?? null)
<div class="row g-2">
    <div class="col-md-3">
        <label class="form-label">Adresstyp *</label>
        <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
            @foreach ($typeOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $address?->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Straße</label>
        <input type="text" name="street" value="{{ old('street', $address?->street) }}" class="form-control form-control-sm @error('street') is-invalid @enderror">
        @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Hausnummer</label>
        <input type="text" name="house_number" value="{{ old('house_number', $address?->house_number) }}" class="form-control form-control-sm @error('house_number') is-invalid @enderror">
        @error('house_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Zusatz</label>
        <input type="text" name="addition" value="{{ old('addition', $address?->addition) }}" class="form-control form-control-sm @error('addition') is-invalid @enderror">
        @error('addition')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">PLZ</label>
        <input type="text" name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}" class="form-control form-control-sm @error('postal_code') is-invalid @enderror">
        @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Ort *</label>
        <input type="text" name="city" value="{{ old('city', $address?->city) }}" class="form-control form-control-sm @error('city') is-invalid @enderror" required>
        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Bundesland</label>
        <input type="text" name="state" value="{{ old('state', $address?->state) }}" class="form-control form-control-sm @error('state') is-invalid @enderror">
        @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Land</label>
        <input type="text" name="country" value="{{ old('country', $address?->country ?? 'Deutschland') }}" class="form-control form-control-sm @error('country') is-invalid @enderror">
        @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Gültig ab</label>
        <input type="date" name="valid_from" value="{{ old('valid_from', $address?->valid_from?->format('Y-m-d')) }}" class="form-control form-control-sm @error('valid_from') is-invalid @enderror">
        @error('valid_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Gültig bis</label>
        <input type="date" name="valid_until" value="{{ old('valid_until', $address?->valid_until?->format('Y-m-d')) }}" class="form-control form-control-sm @error('valid_until') is-invalid @enderror">
        @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_primary" id="address-primary-{{ $address?->id ?? 'new' }}" value="1"
                   class="form-check-input" @checked(old('is_primary', $address?->is_primary))>
            <label class="form-check-label" for="address-primary-{{ $address?->id ?? 'new' }}">Hauptadresse</label>
        </div>
    </div>
</div>
