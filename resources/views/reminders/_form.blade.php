@php($reminder = $reminder ?? null)
@php($preset = $preset ?? [])

<div class="row g-3">
    <div class="col-12 col-md-8">
        <label class="form-label" for="title">Titel *</label>
        <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
               value="{{ old('title', $reminder?->title) }}" required maxlength="255">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="due_date">Fällig am *</label>
        <input type="date" id="due_date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
               value="{{ old('due_date', $reminder?->due_date?->toDateString()) }}" required>
        @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="due_time">Uhrzeit</label>
        <input type="time" id="due_time" name="due_time" class="form-control @error('due_time') is-invalid @enderror"
               value="{{ old('due_time', $reminder?->due_time ? substr($reminder->due_time, 0, 5) : null) }}">
        @error('due_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Beschreibung</label>
        <textarea id="description" name="description" rows="3"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $reminder?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="assigned_to">Zugewiesen an *</label>
        <select id="assigned_to" name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
            <option value="">Bitte wählen</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(old('assigned_to', $reminder?->assigned_to ?? auth()->id()) == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="priority">Priorität *</label>
        <select id="priority" name="priority" class="form-select @error('priority') is-invalid @enderror" required>
            @foreach (['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch'] as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $reminder?->priority?->value ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label" for="remindable_type">Bezug (optional)</label>
        @php($currentType = old('remindable_type', $preset['remindable_type'] ?? array_search($reminder?->remindable_type, \App\Http\Requests\Organisation\ReminderRequest::REMINDABLE_TYPES, true)))
        <select id="remindable_type" name="remindable_type" class="form-select @error('remindable_type') is-invalid @enderror">
            <option value="">Kein Bezug</option>
            @foreach (['entity' => 'Person/Unternehmen', 'loan' => 'Darlehen', 'contract' => 'Vertrag', 'resolution' => 'Beschluss', 'share_transaction' => 'Aktienbewegung', 'investment' => 'Beteiligung'] as $value => $label)
                <option value="{{ $value }}" @selected($currentType === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('remindable_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label" for="remindable_id">Bezugs-ID</label>
        <input type="number" id="remindable_id" name="remindable_id" min="1"
               class="form-control @error('remindable_id') is-invalid @enderror"
               value="{{ old('remindable_id', $preset['remindable_id'] ?? $reminder?->remindable_id) }}">
        @error('remindable_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Interne Nummer des Bezugsobjekts (wird auf Detailseiten vorbelegt).</div>
    </div>
</div>
