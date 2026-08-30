{{-- Formular: neue Organstellung. $perspective 'person' => Unternehmen wählen, 'company' => Person wählen. --}}
@php
    $roleOptions = $roleOptions ?? \App\Enums\OrganizationRoleType::cases();
    $options = $perspective === 'person' ? ($companyOptions ?? collect()) : ($personOptions ?? collect());
    $fieldName = $perspective === 'person' ? 'company_entity_id' : 'person_entity_id';
@endphp
<form method="POST" action="{{ route($prefix.'.organization-roles.store', $entity) }}">
    @csrf
    <input type="hidden" name="_form" value="orgrole-create">
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label">{{ $perspective === 'person' ? 'Unternehmen *' : 'Person *' }}</label>
            <select name="{{ $fieldName }}" class="form-select form-select-sm @error($fieldName) is-invalid @enderror" required>
                <option value="">Bitte wählen</option>
                @foreach ($options as $option)
                    <option value="{{ $option->id }}" @selected((string) old($fieldName) === (string) $option->id)>{{ $option->display_name }}</option>
                @endforeach
            </select>
            @error($fieldName)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Rolle *</label>
            <select name="role" class="form-select form-select-sm @error('role') is-invalid @enderror" required>
                @foreach ($roleOptions as $roleOption)
                    <option value="{{ $roleOption->value }}" @selected(old('role') === $roleOption->value)>{{ $roleOption->label() }}</option>
                @endforeach
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Beginn</label>
            <input type="date" name="started_on" value="{{ old('started_on') }}"
                   class="form-control form-control-sm @error('started_on') is-invalid @enderror">
            @error('started_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Vertretungsregel</label>
            <input type="text" name="representation_rule" value="{{ old('representation_rule') }}"
                   class="form-control form-control-sm @error('representation_rule') is-invalid @enderror"
                   placeholder="z. B. gemeinsam mit einem weiteren Geschäftsführer">
            @error('representation_rule')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Notiz</label>
            <input type="text" name="note" value="{{ old('note') }}"
                   class="form-control form-control-sm @error('note') is-invalid @enderror">
            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" name="sole_representation" id="orgrole-sole" value="1" class="form-check-input" @checked(old('sole_representation'))>
                <label class="form-check-label" for="orgrole-sole">Einzelvertretung</label>
            </div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" name="exemption_181" id="orgrole-181" value="1" class="form-check-input" @checked(old('exemption_181'))>
                <label class="form-check-label" for="orgrole-181">
                    Befreiung § 181 BGB
                    <x-help-icon text="Reine Information, keine rechtliche Bewertung" />
                </label>
            </div>
        </div>
    </div>
    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Organstellung anlegen</button>
</form>
