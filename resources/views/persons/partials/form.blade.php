{{-- Formularfelder einer Person (Abschnitt 6 Masterprompt). $person kann null sein (Neuanlage). --}}
@php
    $person = $person ?? null;
    $entity = $entity ?? null;
@endphp

<div class="card mb-3">
    <div class="card-header">Stammdaten</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label for="salutation" class="form-label">Anrede</label>
                <select name="salutation" id="salutation" class="form-select @error('salutation') is-invalid @enderror">
                    <option value="">Bitte wählen</option>
                    @foreach (['Herr', 'Frau', 'Divers'] as $option)
                        <option value="{{ $option }}" @selected(old('salutation', $person?->salutation) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('salutation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="title" class="form-label">Titel</label>
                <input type="text" name="title" id="title" value="{{ old('title', $person?->title) }}"
                       class="form-control @error('title') is-invalid @enderror" placeholder="z. B. Dr.">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="first_name" class="form-label">Vorname *</label>
                <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $person?->first_name) }}"
                       class="form-control @error('first_name') is-invalid @enderror" required>
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="middle_names" class="form-label">Weitere Vornamen</label>
                <input type="text" name="middle_names" id="middle_names" value="{{ old('middle_names', $person?->middle_names) }}"
                       class="form-control @error('middle_names') is-invalid @enderror">
                @error('middle_names')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="last_name" class="form-label">Nachname *</label>
                <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $person?->last_name) }}"
                       class="form-control @error('last_name') is-invalid @enderror" required>
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="birth_name" class="form-label">Geburtsname</label>
                <input type="text" name="birth_name" id="birth_name" value="{{ old('birth_name', $person?->birth_name) }}"
                       class="form-control @error('birth_name') is-invalid @enderror">
                @error('birth_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="date_of_birth" class="form-label">Geburtsdatum</label>
                <input type="date" name="date_of_birth" id="date_of_birth"
                       value="{{ old('date_of_birth', $person?->date_of_birth?->format('Y-m-d')) }}"
                       class="form-control @error('date_of_birth') is-invalid @enderror">
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="place_of_birth" class="form-label">Geburtsort</label>
                <input type="text" name="place_of_birth" id="place_of_birth" value="{{ old('place_of_birth', $person?->place_of_birth) }}"
                       class="form-control @error('place_of_birth') is-invalid @enderror">
                @error('place_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="nationality" class="form-label">Staatsangehörigkeit</label>
                <input type="text" name="nationality" id="nationality" value="{{ old('nationality', $person?->nationality) }}"
                       class="form-control @error('nationality') is-invalid @enderror" placeholder="z. B. deutsch">
                @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="gender" class="form-label">Geschlecht</label>
                <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror">
                    <option value="">Keine Angabe</option>
                    @foreach (['männlich', 'weiblich', 'divers'] as $option)
                        <option value="{{ $option }}" @selected(old('gender', $person?->gender) === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="marital_status" class="form-label">Familienstand</label>
                <select name="marital_status" id="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                    <option value="">Keine Angabe</option>
                    @foreach (['ledig', 'verheiratet', 'eingetragene Lebenspartnerschaft', 'geschieden', 'verwitwet'] as $option)
                        <option value="{{ $option }}" @selected(old('marital_status', $person?->marital_status) === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                @error('marital_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Organisation</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="tags" class="form-label">Tags <x-help-icon text="Mehrere Tags mit Komma trennen, z. B. Darlehensgeber, Familie" /></label>
                <input type="text" name="tags" id="tags"
                       value="{{ old('tags', is_array($entity?->tags) ? implode(', ', $entity->tags) : '') }}"
                       class="form-control @error('tags') is-invalid @enderror" placeholder="Tag1, Tag2">
                @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if (auth()->user()->isInternal())
                <div class="col-12">
                    <label for="notes" class="form-label">Interne Notizen <x-help-icon text="Nur für interne Rollen sichtbar, nie für externe Benutzer" /></label>
                    <textarea name="notes" id="notes" rows="3"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $entity?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
    </div>
</div>
