@php($recurringDefault = old('recurring', ($entry?->recurring ?? true) ? '1' : '0') === '1')
<div class="row g-3">
    <div class="col-12 col-md-8">
        <label class="form-label" for="title">Titel *</label>
        <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
               value="{{ old('title', $entry?->title) }}" required maxlength="255"
               placeholder="z. B. Welthundetag">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Der Titel erscheint in der Fußzeile im Satz "Heute: ...".</div>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="country">Land</label>
        <input type="text" id="country" name="country" class="form-control @error('country') is-invalid @enderror"
               value="{{ old('country', $entry?->country) }}" maxlength="255" placeholder="z. B. Deutschland">
        @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="description">Beschreibung</label>
        <textarea id="description" name="description" rows="3" maxlength="2000"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $entry?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="source">
            Quelle *
            <x-help-icon text="Pflichtfeld. Ohne belegte Quelle wird kein Eintrag angezeigt, damit keine unbelegten Angaben in der Anwendung erscheinen." />
        </label>
        <input type="text" id="source" name="source" class="form-control @error('source') is-invalid @enderror"
               value="{{ old('source', $entry?->source) }}" required maxlength="255"
               placeholder="Herausgeber, Veröffentlichung oder Adresse der Fundstelle">
        @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="recurring" value="0">
            <input class="form-check-input" type="checkbox" name="recurring" value="1" id="recurring"
                   @checked($recurringDefault)>
            <label class="form-check-label" for="recurring">
                Jährlich wiederkehrend (Monat und Tag)
            </label>
        </div>
        <div class="form-text">
            Ohne Häkchen gilt der Eintrag nur für ein einzelnes Datum.
        </div>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label" for="month_day">Monat und Tag (MM-TT)</label>
        <input type="text" id="month_day" name="month_day" class="form-control @error('month_day') is-invalid @enderror"
               value="{{ old('month_day', $entry?->month_day) }}" maxlength="5" placeholder="10-30"
               pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])">
        @error('month_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Pflicht bei wiederkehrenden Einträgen.</div>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="specific_date">Datum</label>
        <input type="date" id="specific_date" name="specific_date" class="form-control @error('specific_date') is-invalid @enderror"
               value="{{ old('specific_date', $entry?->specific_date?->format('Y-m-d')) }}">
        @error('specific_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Pflicht bei einmaligen Einträgen.</div>
    </div>
    <div class="col-12 col-md-4 d-flex align-items-center">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                   @checked(old('is_active', ($entry?->is_active ?? true) ? '1' : '0') === '1')>
            <label class="form-check-label" for="is_active">Aktiv</label>
        </div>
    </div>
</div>
