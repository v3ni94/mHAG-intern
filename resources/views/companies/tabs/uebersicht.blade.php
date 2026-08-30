{{-- Unternehmensakte: Übersicht --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi-card label="Darlehen" :value="$entity->loans_as_lender_count + $entity->loans_as_borrower_count"
                    icon="bi-cash-stack" hint="als Geber und Nehmer" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi-card label="Organe / Funktionen" :value="$entity->organization_roles_as_company_count" icon="bi-person-badge" />
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
            <div class="card-header">Unternehmen</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Firmenname</dt>
                    <dd class="col-sm-8">{{ $entity->company?->name }}</dd>
                    <dt class="col-sm-4">Rechtsform</dt>
                    <dd class="col-sm-8">{{ $entity->company?->legal_form ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Register</dt>
                    <dd class="col-sm-8">
                        {{ implode(' · ', array_filter([$entity->company?->commercial_register, $entity->company?->register_number, $entity->company?->register_court])) ?: 'Nicht erfasst' }}
                    </dd>
                    <dt class="col-sm-4">Umsatzsteuer-ID</dt>
                    <dd class="col-sm-8">{{ $entity->company?->vat_id ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Branche</dt>
                    <dd class="col-sm-8">{{ $entity->company?->industry ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Unternehmens-Nr.</dt>
                    <dd class="col-sm-8">{{ $entity->internal_number }}</dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">@include('persons.partials.entity-status', ['entity' => $entity])</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Anschrift und Kontakt</div>
            <div class="card-body">
                @php($primaryAddress = $entity->primaryAddress())
                <dl class="row mb-0">
                    <dt class="col-sm-4">Geschäftsanschrift</dt>
                    <dd class="col-sm-8">
                        @if ($primaryAddress)
                            {{ $primaryAddress->oneLine() }}
                        @else
                            <span class="text-muted">Keine Adresse hinterlegt</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">E-Mail</dt>
                    <dd class="col-sm-8">{{ $entity->company?->email ?: ($entity->primaryEmail() ?: 'Nicht erfasst') }}</dd>
                    <dt class="col-sm-4">Telefon</dt>
                    <dd class="col-sm-8">{{ $entity->company?->phone ?: 'Nicht erfasst' }}</dd>
                    <dt class="col-sm-4">Website</dt>
                    <dd class="col-sm-8">
                        @if ($entity->company?->website)
                            <a href="{{ $entity->company->website }}" target="_blank" rel="noopener">{{ $entity->company->website }}</a>
                        @else
                            Nicht erfasst
                        @endif
                    </dd>
                    <dt class="col-sm-4">Steuerdaten</dt>
                    <dd class="col-sm-8">
                        {{ implode(' · ', array_filter([$entity->company?->tax_number, $entity->taxDetail?->tax_office])) ?: 'Nicht erfasst' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
