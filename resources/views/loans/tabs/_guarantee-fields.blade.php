{{-- Felder für Bürgschaften; Parameter: $guarantee (nullable), $suffix, $entities --}}
<div class="col-md-3">
    <label class="form-label small mb-1" for="gua-guarantor{{ $suffix }}">Bürge</label>
    <select id="gua-guarantor{{ $suffix }}" name="guarantor_entity_id" class="form-select form-select-sm" required>
        <option value="">Bitte wählen</option>
        @foreach ($entities as $entity)
            <option value="{{ $entity->id }}" @selected($guarantee?->guarantor_entity_id === $entity->id)>{{ $entity->display_name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="gua-type{{ $suffix }}">Bürgschaftsart</label>
    <input type="text" id="gua-type{{ $suffix }}" name="guarantee_type" class="form-control form-control-sm"
           value="{{ $guarantee?->guarantee_type }}" placeholder="z. B. selbstschuldnerisch">
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="gua-max{{ $suffix }}">Höchstbetrag (EUR)</label>
    <input type="text" inputmode="decimal" id="gua-max{{ $suffix }}" name="max_amount" class="form-control form-control-sm"
           value="{{ $guarantee !== null && $guarantee->max_amount !== null ? \App\Support\Money::format($guarantee->max_amount, 'EUR', false) : '' }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="gua-from{{ $suffix }}">Beginn</label>
    <input type="date" id="gua-from{{ $suffix }}" name="valid_from" class="form-control form-control-sm"
           value="{{ $guarantee?->valid_from?->format('Y-m-d') }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="gua-until{{ $suffix }}">Ende</label>
    <input type="date" id="gua-until{{ $suffix }}" name="valid_until" class="form-control form-control-sm"
           value="{{ $guarantee?->valid_until?->format('Y-m-d') }}">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="gua-status{{ $suffix }}">Status</label>
    <select id="gua-status{{ $suffix }}" name="status" class="form-select form-select-sm" required>
        <option value="active" @selected(($guarantee?->status ?? 'active') === 'active')>Aktiv</option>
        <option value="released" @selected($guarantee?->status === 'released')>Freigegeben</option>
        <option value="expired" @selected($guarantee?->status === 'expired')>Abgelaufen</option>
    </select>
</div>
