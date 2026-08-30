{{-- Formularfelder eines Unternehmens (Abschnitt 7 Masterprompt). $company kann null sein. --}}
@php
    $company = $company ?? null;
    $entity = $entity ?? null;
@endphp

<div class="card mb-3">
    <div class="card-header">Stammdaten</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Firmenname *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $company?->name) }}"
                       class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="short_name" class="form-label">Kurzname</label>
                <input type="text" name="short_name" id="short_name" value="{{ old('short_name', $company?->short_name) }}"
                       class="form-control @error('short_name') is-invalid @enderror">
                @error('short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="legal_form" class="form-label">Rechtsform</label>
                <input type="text" name="legal_form" id="legal_form" value="{{ old('legal_form', $company?->legal_form) }}"
                       class="form-control @error('legal_form') is-invalid @enderror" placeholder="z. B. GmbH, AG">
                @error('legal_form')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="founded_on" class="form-label">Gründungsdatum</label>
                <input type="date" name="founded_on" id="founded_on"
                       value="{{ old('founded_on', $company?->founded_on?->format('Y-m-d')) }}"
                       class="form-control @error('founded_on') is-invalid @enderror">
                @error('founded_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="industry" class="form-label">Branche</label>
                <input type="text" name="industry" id="industry" value="{{ old('industry', $company?->industry) }}"
                       class="form-control @error('industry') is-invalid @enderror">
                @error('industry')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Register und Steuern</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="commercial_register" class="form-label">Handelsregister</label>
                <input type="text" name="commercial_register" id="commercial_register"
                       value="{{ old('commercial_register', $company?->commercial_register) }}"
                       class="form-control @error('commercial_register') is-invalid @enderror" placeholder="z. B. HRB">
                @error('commercial_register')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="register_number" class="form-label">Registernummer</label>
                <input type="text" name="register_number" id="register_number"
                       value="{{ old('register_number', $company?->register_number) }}"
                       class="form-control @error('register_number') is-invalid @enderror">
                @error('register_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="register_court" class="form-label">Registergericht</label>
                <input type="text" name="register_court" id="register_court"
                       value="{{ old('register_court', $company?->register_court) }}"
                       class="form-control @error('register_court') is-invalid @enderror">
                @error('register_court')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="tax_number" class="form-label">Steuernummer</label>
                <input type="text" name="tax_number" id="tax_number" value="{{ old('tax_number', $company?->tax_number) }}"
                       class="form-control @error('tax_number') is-invalid @enderror">
                @error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="vat_id" class="form-label">Umsatzsteuer-ID</label>
                <input type="text" name="vat_id" id="vat_id" value="{{ old('vat_id', $company?->vat_id) }}"
                       class="form-control @error('vat_id') is-invalid @enderror">
                @error('vat_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="business_id" class="form-label">Wirtschafts-ID</label>
                <input type="text" name="business_id" id="business_id" value="{{ old('business_id', $company?->business_id) }}"
                       class="form-control @error('business_id') is-invalid @enderror">
                @error('business_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Kontakt</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="email" class="form-label">E-Mail</label>
                <input type="email" name="email" id="email" value="{{ old('email', $company?->email) }}"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="phone" class="form-label">Telefon</label>
                <input type="text" name="phone" id="phone" value="{{ old('phone', $company?->phone) }}"
                       class="form-control @error('phone') is-invalid @enderror">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="fax" class="form-label">Fax</label>
                <input type="text" name="fax" id="fax" value="{{ old('fax', $company?->fax) }}"
                       class="form-control @error('fax') is-invalid @enderror">
                @error('fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="website" class="form-label">Website</label>
                <input type="text" name="website" id="website" value="{{ old('website', $company?->website) }}"
                       class="form-control @error('website') is-invalid @enderror" placeholder="https://">
                @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Organisation</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="tags" class="form-label">Tags <x-help-icon text="Mehrere Tags mit Komma trennen" /></label>
                <input type="text" name="tags" id="tags"
                       value="{{ old('tags', is_array($entity?->tags) ? implode(', ', $entity->tags) : '') }}"
                       class="form-control @error('tags') is-invalid @enderror" placeholder="Tag1, Tag2">
                @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if (auth()->user()->isInternal())
                <div class="col-12">
                    <label for="notes" class="form-label">Interne Notizen <x-help-icon text="Nur für interne Rollen sichtbar" /></label>
                    <textarea name="notes" id="notes" rows="3"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $entity?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
    </div>
</div>
