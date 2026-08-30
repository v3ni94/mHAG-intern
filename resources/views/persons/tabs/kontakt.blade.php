{{-- Akte: Kontaktdaten (gemeinsam nutzbar, $routePrefix steuert die Routen) --}}
@php
    $prefix = $routePrefix ?? 'persons';
    $canEdit = auth()->user()->can($prefix === 'companies' ? 'companies.update' : 'persons.update');
    $typeOptions = \App\Http\Requests\MasterData\ContactDetailRequest::typeOptions();
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Kontaktdaten</span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#contact-create">
                <i class="bi bi-plus-lg"></i> Kontakt hinzufügen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'contact-create' ? 'show' : '' }}" id="contact-create">
            <div class="card-body hairline-top">
                <form method="POST" action="{{ route($prefix.'.contacts.store', $entity) }}">
                    @csrf
                    <input type="hidden" name="_form" value="contact-create">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">Kontaktart *</label>
                            <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Wert *</label>
                            <input type="text" name="value" value="{{ old('value') }}" class="form-control form-control-sm @error('value') is-invalid @enderror"
                                   placeholder="z. B. max@beispiel.de oder +49 170 1234567" required>
                            @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bezeichnung</label>
                            <input type="text" name="label" value="{{ old('label') }}" class="form-control form-control-sm @error('label') is-invalid @enderror" placeholder="z. B. dienstlich">
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_primary" id="contact-primary-new" value="1" class="form-check-input" @checked(old('is_primary'))>
                                <label class="form-check-label" for="contact-primary-new">Hauptkontakt</label>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    @if ($entity->contactDetails->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-envelope" message="Keine Kontaktdaten hinterlegt." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Art</th>
                    <th>Wert</th>
                    <th>Bezeichnung</th>
                    <th>Hauptkontakt</th>
                    @if ($canEdit)<th class="text-end">Aktionen</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($entity->contactDetails as $contact)
                    <tr>
                        <td>{{ $typeOptions[$contact->type] ?? $contact->type }}</td>
                        <td>
                            @if (in_array($contact->type, ['email', 'email_alt']))
                                <a href="mailto:{{ $contact->value }}">{{ $contact->value }}</a>
                            @elseif (in_array($contact->type, ['phone', 'mobile']))
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->value) }}">{{ $contact->value }}</a>
                            @else
                                {{ $contact->value }}
                            @endif
                        </td>
                        <td>{{ $contact->label }}</td>
                        <td>
                            @if ($contact->is_primary)
                                <x-status-badge severity="info" icon="bi-star-fill" label="Hauptkontakt" />
                            @endif
                        </td>
                        @if ($canEdit)
                            <td class="text-end">
                                <x-confirm-form :action="route($prefix.'.contacts.destroy', [$entity, $contact])" method="DELETE"
                                                confirm="Diesen Kontakt wirklich löschen?" icon="bi-trash" label="" />
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
