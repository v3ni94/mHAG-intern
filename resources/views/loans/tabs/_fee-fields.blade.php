{{-- Gemeinsame Gebührenfelder für Anlage und Bearbeitung; Parameter: $fee (nullable), $suffix --}}
<div class="col-md-2">
    <label class="form-label small mb-1" for="fee-type{{ $suffix }}">Art</label>
    <select id="fee-type{{ $suffix }}" name="type" class="form-select form-select-sm" required>
        @foreach (\App\Enums\FeeType::cases() as $type)
            <option value="{{ $type->value }}" @selected($fee?->type === $type)>{{ $type->label() }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="fee-name{{ $suffix }}">Bezeichnung</label>
    <input type="text" id="fee-name{{ $suffix }}" name="name" class="form-control form-control-sm"
           value="{{ $fee?->name }}" required>
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="fee-amount{{ $suffix }}">Betrag (EUR)</label>
    <input type="text" inputmode="decimal" id="fee-amount{{ $suffix }}" name="amount" class="form-control form-control-sm"
           value="{{ $fee?->amount !== null && $fee !== null ? \App\Support\Money::format($fee->amount, 'EUR', false) : '' }}" placeholder="oder Prozentsatz">
</div>
<div class="col-md-1">
    <label class="form-label small mb-1" for="fee-percentage{{ $suffix }}">Prozent</label>
    <input type="text" inputmode="decimal" id="fee-percentage{{ $suffix }}" name="percentage" class="form-control form-control-sm"
           value="{{ $fee?->percentage !== null && $fee !== null ? rtrim(rtrim(str_replace('.', ',', (string) $fee->percentage), '0'), ',') : '' }}">
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="fee-recurrence{{ $suffix }}">Wiederkehr</label>
    <select id="fee-recurrence{{ $suffix }}" name="recurrence" class="form-select form-select-sm" required>
        <option value="one_time" @selected($fee?->recurrence === 'one_time')>einmalig</option>
        <option value="monthly" @selected($fee?->recurrence === 'monthly')>monatlich</option>
        <option value="quarterly" @selected($fee?->recurrence === 'quarterly')>quartalsweise</option>
        <option value="annual" @selected($fee?->recurrence === 'annual')>jährlich</option>
    </select>
</div>
<div class="col-md-2">
    <label class="form-label small mb-1" for="fee-due{{ $suffix }}">Fällig am</label>
    <input type="date" id="fee-due{{ $suffix }}" name="due_date" class="form-control form-control-sm"
           value="{{ $fee?->due_date?->format('Y-m-d') }}">
</div>
