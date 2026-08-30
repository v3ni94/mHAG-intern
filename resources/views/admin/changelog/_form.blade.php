<div class="row g-3">
    <div class="col-12 col-md-4">
        <label class="form-label" for="version">Version *</label>
        <input type="text" id="version" name="version" class="form-control @error('version') is-invalid @enderror"
               value="{{ old('version', $entry?->version) }}" required maxlength="50" placeholder="z. B. 1.1.0">
        @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="released_on">Datum *</label>
        <input type="date" id="released_on" name="released_on" class="form-control @error('released_on') is-invalid @enderror"
               value="{{ old('released_on', $entry?->released_on?->format('Y-m-d')) }}" required>
        @error('released_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="changes">
            Änderungen *
            <x-help-icon text="Markdown ist erlaubt. Üblich sind zwei Abschnitte: Neue Funktionen und Fehlerbehebungen, jeweils als Liste." />
        </label>
        <textarea id="changes" name="changes" rows="12" required maxlength="20000"
                  class="form-control font-monospace @error('changes') is-invalid @enderror"
                  placeholder="## Neue Funktionen&#10;- ...&#10;&#10;## Fehlerbehebungen&#10;- ...">{{ old('changes', $entry?->changes) }}</textarea>
        @error('changes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">
            Die Einträge erscheinen unter Hilfe, Was ist neu? Bitte nur tatsächlich ausgelieferte Änderungen erfassen.
        </div>
    </div>
</div>
