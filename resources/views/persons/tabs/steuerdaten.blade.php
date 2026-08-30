{{-- Personenakte: Steuerdaten --}}
@php
    $prefix = $routePrefix ?? 'persons';
    $canEdit = auth()->user()->can($prefix === 'companies' ? 'companies.update' : 'persons.update');
    $taxDetail = $entity->taxDetail;
@endphp

<div class="card">
    <div class="card-header">Steuerdaten</div>
    <div class="card-body">
        @if ($canEdit)
            <form method="POST" action="{{ route($prefix.'.tax-details.store', $entity) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="tax_id" class="form-label">Steuer-ID</label>
                        <input type="text" name="tax_id" id="tax_id" value="{{ old('tax_id', $taxDetail?->tax_id) }}"
                               class="form-control @error('tax_id') is-invalid @enderror">
                        @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tax_number" class="form-label">Steuernummer</label>
                        <input type="text" name="tax_number" id="tax_number" value="{{ old('tax_number', $taxDetail?->tax_number) }}"
                               class="form-control @error('tax_number') is-invalid @enderror">
                        @error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tax_office" class="form-label">Finanzamt</label>
                        <input type="text" name="tax_office" id="tax_office" value="{{ old('tax_office', $taxDetail?->tax_office) }}"
                               class="form-control @error('tax_office') is-invalid @enderror">
                        @error('tax_office')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="tax_note" class="form-label">Weitere steuerliche Referenzen / Notiz</label>
                        <textarea name="note" id="tax_note" rows="2" class="form-control @error('note') is-invalid @enderror">{{ old('note', $taxDetail?->note) }}</textarea>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Steuerdaten speichern</button>
                </div>
            </form>
            @if ($taxDetail)
                <div class="mt-2">
                    <x-confirm-form :action="route($prefix.'.tax-details.destroy', $entity)" method="DELETE"
                                    confirm="Die Steuerdaten dieser Akte wirklich entfernen?"
                                    label="Steuerdaten entfernen" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                </div>
            @endif
        @else
            @if ($taxDetail)
                <dl class="row mb-0">
                    <dt class="col-sm-3">Steuer-ID</dt>
                    <dd class="col-sm-9">{{ $taxDetail->tax_id ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-3">Steuernummer</dt>
                    <dd class="col-sm-9">{{ $taxDetail->tax_number ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-3">Finanzamt</dt>
                    <dd class="col-sm-9">{{ $taxDetail->tax_office ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-3">Notiz</dt>
                    <dd class="col-sm-9">{{ $taxDetail->note ?: 'Keine' }}</dd>
                </dl>
            @else
                <x-empty-state icon="bi-percent" message="Keine Steuerdaten hinterlegt." />
            @endif
        @endif
    </div>
</div>
