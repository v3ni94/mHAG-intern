{{-- Personenakte: Übersicht --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi-card label="Darlehen" :value="$entity->loans_as_lender_count + $entity->loans_as_borrower_count"
                    icon="bi-cash-stack" hint="als Geber und Nehmer" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi-card label="Organstellungen" :value="$entity->organization_roles_as_person_count" icon="bi-person-badge" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi-card label="Bankkonten" :value="$entity->bankAccounts->count()" icon="bi-bank" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi-card label="Dokumente" :value="$entity->document_links_count" icon="bi-folder2-open" />
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Person</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8">{{ trim(implode(' ', array_filter([$entity->person?->salutation, $entity->person?->title, $entity->person?->first_name, $entity->person?->last_name]))) }}</dd>
                    <dt class="col-sm-4">Geburtsdatum</dt>
                    <dd class="col-sm-8">{{ format_date($entity->person?->date_of_birth) ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Geburtsort</dt>
                    <dd class="col-sm-8">{{ $entity->person?->place_of_birth ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Staatsangehörigkeit</dt>
                    <dd class="col-sm-8">{{ $entity->person?->nationality ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Personen-Nr.</dt>
                    <dd class="col-sm-8">{{ $entity->internal_number }}</dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">@include('persons.partials.entity-status', ['entity' => $entity])</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Adresse und Kontakt</div>
            <div class="card-body">
                @php($primaryAddress = $entity->primaryAddress())
                <dl class="row mb-0">
                    <dt class="col-sm-4">Hauptadresse</dt>
                    <dd class="col-sm-8">
                        @if ($primaryAddress)
                            {{ $primaryAddress->oneLine() }}
                        @else
                            <span class="text-muted">Keine Adresse hinterlegt</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">E-Mail</dt>
                    <dd class="col-sm-8">{{ $entity->primaryEmail() ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Telefon</dt>
                    <dd class="col-sm-8">
                        {{ $entity->contactDetails->whereIn('type', ['phone', 'mobile'])->sortByDesc('is_primary')->first()?->value ?: 'Nicht erfasst' }}
                    </dd>
                    <dt class="col-sm-4">Steuerdaten</dt>
                    <dd class="col-sm-8">
                        @if ($entity->taxDetail)
                            {{ implode(' · ', array_filter([$entity->taxDetail->tax_id, $entity->taxDetail->tax_number])) ?: 'Erfasst' }}
                        @else
                            <span class="text-muted">Nicht erfasst</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">Identitätsnachweise</dt>
                    <dd class="col-sm-8">
                        @php($expiring = $entity->identityDocuments->filter(fn ($d) => $d->expires_on && $d->expires_on->isPast()))
                        {{ $entity->identityDocuments->count() }} erfasst
                        @if ($expiring->isNotEmpty())
                            <x-status-badge severity="danger" icon="bi-exclamation-octagon-fill" :label="$expiring->count().' abgelaufen'" class="ms-1" />
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
