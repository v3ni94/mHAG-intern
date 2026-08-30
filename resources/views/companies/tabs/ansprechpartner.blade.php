{{-- Unternehmensakte: Ansprechpartner (Personen mit Rolle Ansprechpartner) und Kontaktdaten --}}
@php($canEdit = auth()->user()->can('companies.update'))

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Ansprechpartner</span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#contactperson-create">
                <i class="bi bi-plus-lg"></i> Ansprechpartner zuordnen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'contactperson-create' ? 'show' : '' }}" id="contactperson-create">
            <div class="card-body hairline-top">
                <form method="POST" action="{{ route('companies.organization-roles.store', $entity) }}">
                    @csrf
                    <input type="hidden" name="_form" value="contactperson-create">
                    <input type="hidden" name="role" value="contact_person">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">Person *</label>
                            <select name="person_entity_id" class="form-select form-select-sm @error('person_entity_id') is-invalid @enderror" required>
                                <option value="">Bitte wählen</option>
                                @foreach (($personOptions ?? collect()) as $option)
                                    <option value="{{ $option->id }}" @selected((string) old('person_entity_id') === (string) $option->id)>{{ $option->display_name }}</option>
                                @endforeach
                            </select>
                            @error('person_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('role')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Beginn</label>
                            <input type="date" name="started_on" value="{{ old('started_on') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notiz</label>
                            <input type="text" name="note" value="{{ old('note') }}" class="form-control form-control-sm" placeholder="z. B. Zuständigkeit">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Zuordnen</button>
                </form>
            </div>
        </div>
    @endif

    @php($contactPersons = $entity->organizationRolesAsCompany)
    @if ($contactPersons->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-person-lines-fill" message="Keine Ansprechpartner zugeordnet." />
        </div>
    @else
        @include('persons.tabs.partials.organization-roles-table', [
            'roles' => $contactPersons,
            'perspective' => 'company',
            'prefix' => 'companies',
            'canEdit' => $canEdit,
        ])
    @endif
</div>

{{-- Allgemeine Kontaktdaten des Unternehmens (contact_details) --}}
@include('persons.tabs.kontakt', ['routePrefix' => 'companies'])
