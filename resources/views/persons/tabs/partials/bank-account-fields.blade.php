{{-- Bankkonto-Felder (Neuanlage und Bearbeitung) --}}
@php($account = $account ?? null)
<div class="row g-2">
    <div class="col-md-4">
        <label class="form-label">Kontoinhaber *</label>
        <input type="text" name="account_holder" value="{{ old('account_holder', $account?->account_holder ?? $entity->display_name) }}"
               class="form-control form-control-sm @error('account_holder') is-invalid @enderror" required>
        @error('account_holder')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">IBAN *</label>
        <input type="text" name="iban" value="{{ old('iban', $account?->iban) }}"
               class="form-control form-control-sm @error('iban') is-invalid @enderror"
               placeholder="DE89 3704 0044 0532 0130 00" required>
        @error('iban')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">BIC</label>
        <input type="text" name="bic" value="{{ old('bic', $account?->bic) }}"
               class="form-control form-control-sm @error('bic') is-invalid @enderror">
        @error('bic')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Währung *</label>
        <input type="text" name="currency" value="{{ old('currency', $account?->currency ?? 'EUR') }}" maxlength="3"
               class="form-control form-control-sm @error('currency') is-invalid @enderror" required>
        @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Bank</label>
        <input type="text" name="bank_name" value="{{ old('bank_name', $account?->bank_name) }}"
               class="form-control form-control-sm @error('bank_name') is-invalid @enderror">
        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Notiz</label>
        <input type="text" name="note" value="{{ old('note', $account?->note) }}"
               class="form-control form-control-sm @error('note') is-invalid @enderror">
        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_primary" id="bank-primary-{{ $account?->id ?? 'new' }}" value="1"
                   class="form-check-input" @checked(old('is_primary', $account?->is_primary))>
            <label class="form-check-label" for="bank-primary-{{ $account?->id ?? 'new' }}">Hauptkonto</label>
        </div>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_active" id="bank-active-{{ $account?->id ?? 'new' }}" value="1"
                   class="form-check-input" @checked(old('is_active', $account?->is_active ?? true))>
            <label class="form-check-label" for="bank-active-{{ $account?->id ?? 'new' }}">Aktiv</label>
        </div>
    </div>
</div>
