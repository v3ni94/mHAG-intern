{{--
    Anschrift beim Anlegen einer Person oder eines Unternehmens
    (Masterprompt 6 und 7: Adressen bzw. Geschäftsanschrift gehören zu den
    Stammdaten). Nur beim Anlegen sichtbar; danach werden Adressen im Tab
    "Adressen" gepflegt, weil dort mehrere Adressen mit Gültigkeitszeiträumen
    möglich sind.

    Erwartete Variable: $addressType (Vorbelegung der Adressart)
--}}
@php
    $addressType = $addressType ?? 'main';
    $typeOptions = \App\Http\Requests\MasterData\AddressRequest::typeOptions();
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Anschrift</span>
        <span class="versal-label">optional</span>
    </div>
    <div class="card-body">
        <p class="form-text mt-0">
            Wird eine Straße oder ein Ort eingetragen, legt das System die Anschrift als Hauptadresse an.
            Weitere Adressen mit Gültigkeitszeiträumen können anschließend im Tab "Adressen" erfasst werden.
        </p>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="address_type" class="form-label">Adressart</label>
                <select name="address_type" id="address_type" class="form-select @error('address_type') is-invalid @enderror">
                    @foreach ($typeOptions as $wert => $bezeichnung)
                        <option value="{{ $wert }}" @selected(old('address_type', $addressType) === $wert)>{{ $bezeichnung }}</option>
                    @endforeach
                </select>
                @error('address_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="address_street" class="form-label">Straße</label>
                <input type="text" name="address_street" id="address_street" value="{{ old('address_street') }}"
                       class="form-control @error('address_street') is-invalid @enderror">
                @error('address_street')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="address_house_number" class="form-label">Hausnummer</label>
                <input type="text" name="address_house_number" id="address_house_number" value="{{ old('address_house_number') }}"
                       class="form-control @error('address_house_number') is-invalid @enderror">
                @error('address_house_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="address_addition" class="form-label">Adresszusatz</label>
                <input type="text" name="address_addition" id="address_addition" value="{{ old('address_addition') }}"
                       class="form-control @error('address_addition') is-invalid @enderror"
                       placeholder="z. B. Gebäude, Etage, c/o">
                @error('address_addition')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="address_postal_code" class="form-label">PLZ</label>
                <input type="text" name="address_postal_code" id="address_postal_code" value="{{ old('address_postal_code') }}"
                       class="form-control @error('address_postal_code') is-invalid @enderror">
                @error('address_postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="address_city" class="form-label">Ort</label>
                <input type="text" name="address_city" id="address_city" value="{{ old('address_city') }}"
                       class="form-control @error('address_city') is-invalid @enderror">
                @error('address_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="address_country" class="form-label">Land</label>
                <input type="text" name="address_country" id="address_country" value="{{ old('address_country', 'Deutschland') }}"
                       class="form-control @error('address_country') is-invalid @enderror">
                @error('address_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
