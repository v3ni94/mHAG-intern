{{-- Felder für Sicherheiten; Parameter: $security (nullable), $suffix, $entities --}}
<div class="col-md-2">
    <label class="form-label small mb-1" for="sec-type{{ $suffix }}">Art</label>
    <select id="sec-type{{ $suffix }}" name="type" class="form-select form-select-sm" required>
        @foreach (\App\Enums\SecurityType::cases() as $type)
            <option value="{{ $type->value }}" @selected($security?->type === $type)>{{ $type->label() }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="sec-provider{{ $suffix }}">Sicherungsgeber</label>
    <select id="sec-provider{{ $suffix }}" name="provider_entity_id" class="form-select form-select-sm">
        <option value="">Bitte wählen</option>
        @foreach ($entities as $entity)
            <option value="{{ $entity->id }}" @selected($security?->provider_entity_id === $entity->id)>{{ $entity->display_name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="sec-nominal{{ $suffix }}">Nominal (EUR)</label>
    <input type="text" inputmode="decimal" id="sec-nominal{{ $suffix }}" name="nominal_value" class="form-control form-control-sm"
           value="{{ $security !== null && $security->nominal_value !== null ? \App\Support\Money::format($security->nominal_value, 'EUR', false) : '' }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="sec-internal{{ $suffix }}">Intern (EUR)</label>
    <input type="text" inputmode="decimal" id="sec-internal{{ $suffix }}" name="internal_value" class="form-control form-control-sm"
           value="{{ $security !== null && $security->internal_value !== null ? \App\Support\Money::format($security->internal_value, 'EUR', false) : '' }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="sec-rank{{ $suffix }}">Rang</label>
    <input type="text" id="sec-rank{{ $suffix }}" name="rank" class="form-control form-control-sm" value="{{ $security?->rank }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="sec-from{{ $suffix }}">Beginn</label>
    <input type="date" id="sec-from{{ $suffix }}" name="valid_from" class="form-control form-control-sm"
           value="{{ $security?->valid_from?->format('Y-m-d') }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="sec-until{{ $suffix }}">Ende</label>
    <input type="date" id="sec-until{{ $suffix }}" name="valid_until" class="form-control form-control-sm"
           value="{{ $security?->valid_until?->format('Y-m-d') }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="sec-status{{ $suffix }}">Status</label>
    <select id="sec-status{{ $suffix }}" name="status" class="form-select form-select-sm" required>
        <option value="active" @selected(($security?->status ?? 'active') === 'active')>Aktiv</option>
        <option value="released" @selected($security?->status === 'released')>Freigegeben</option>
        <option value="expired" @selected($security?->status === 'expired')>Abgelaufen</option>
    </select>
</div>
<div class="col-12">
    <label class="visually-hidden" for="sec-description{{ $suffix }}">Beschreibung</label>
    <input type="text" id="sec-description{{ $suffix }}" name="description" class="form-control form-control-sm"
           placeholder="Beschreibung (optional)" value="{{ $security?->description }}">
</div>
