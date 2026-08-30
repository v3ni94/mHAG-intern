<div class="row g-3">
    <div class="col-12 col-md-4">
        <label class="form-label" for="category">Kategorie</label>
        <input type="text" id="category" name="category" list="faq-categories"
               class="form-control @error('category') is-invalid @enderror"
               value="{{ old('category', $entry?->category) }}" placeholder="z. B. Darlehen">
        <datalist id="faq-categories">
            @foreach (\App\Models\FaqEntry::query()->select('category')->whereNotNull('category')->distinct()->pluck('category') as $category)
                <option value="{{ $category }}"></option>
            @endforeach
        </datalist>
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-4">
        <label class="form-label" for="visibility">Sichtbarkeit *</label>
        <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror" required>
            @foreach (['all' => 'Alle Benutzer', 'internal' => 'Nur interne Rollen', 'admin' => 'Nur Administratoren', 'supervisory_board' => 'Aufsichtsrat', 'lender' => 'Darlehensgeber', 'borrower' => 'Darlehensnehmer'] as $value => $label)
                <option value="{{ $value }}" @selected(old('visibility', $entry?->visibility ?? 'all') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('visibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="sort">Sortierung</label>
        <input type="number" id="sort" name="sort" min="0" class="form-control @error('sort') is-invalid @enderror"
               value="{{ old('sort', $entry?->sort ?? 0) }}">
        @error('sort')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                   @checked(old('is_active', ($entry?->is_active ?? true) ? '1' : '0') === '1')>
            <label class="form-check-label" for="is_active">Aktiv</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="question">Frage *</label>
        <input type="text" id="question" name="question" class="form-control @error('question') is-invalid @enderror"
               value="{{ old('question', $entry?->question) }}" required maxlength="500">
        @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="answer">Antwort *</label>
        <textarea id="answer" name="answer" rows="6" class="form-control @error('answer') is-invalid @enderror" required>{{ old('answer', $entry?->answer) }}</textarea>
        @error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
