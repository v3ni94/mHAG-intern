{{-- Personenakte: Identitätsdokumente (Abschnitt 6). Ablaufdatum erzeugt eine Wiedervorlage. --}}
@php
    $canEdit = auth()->user()->can('persons.update');
    $types = \App\Enums\IdentityDocumentType::cases();
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Identitätsdokumente
            <x-help-icon text="Beim Speichern eines Ablaufdatums wird automatisch eine Wiedervorlage angelegt. Dateien (Vorder-/Rückseite) werden über das Dokumentenmodul verknüpft." />
        </span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#identity-create">
                <i class="bi bi-plus-lg"></i> Dokument hinzufügen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'identity-create' ? 'show' : '' }}" id="identity-create">
            <div class="card-body hairline-top">
                <form method="POST" action="{{ route('persons.identity-documents.store', $entity) }}">
                    @csrf
                    <input type="hidden" name="_form" value="identity-create">
                    @include('persons.tabs.partials.identity-fields', ['doc' => null, 'types' => $types])
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    @if ($entity->identityDocuments->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-person-vcard" message="Keine Identitätsdokumente hinterlegt." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Dokumenttyp</th>
                    <th>Nummer</th>
                    <th>Ausgestellt</th>
                    <th>Ablauf</th>
                    <th>Behörde / Land</th>
                    <th>Prüfung</th>
                    @if ($canEdit)<th class="text-end">Aktionen</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($entity->identityDocuments as $doc)
                    <tr>
                        <td><x-enum-badge :enum="$doc->type" /></td>
                        <td class="font-monospace">{{ $doc->document_number }}</td>
                        <td>{{ format_date($doc->issued_on) }}</td>
                        <td>
                            {{ format_date($doc->expires_on) }}
                            @if ($doc->expires_on?->isPast())
                                <x-status-badge severity="danger" icon="bi-exclamation-octagon-fill" label="Abgelaufen" class="ms-1" />
                            @elseif ($doc->expires_on && $doc->expires_on->lte(today()->addWeeks(6)))
                                <x-status-badge severity="warning" icon="bi-exclamation-triangle-fill" label="Läuft bald ab" class="ms-1" />
                            @endif
                        </td>
                        <td>{{ implode(' · ', array_filter([$doc->authority, $doc->country])) }}</td>
                        <td>
                            @if ($doc->verified)
                                <x-status-badge severity="success" icon="bi-patch-check-fill" label="Geprüft" />
                                <div class="text-muted small">
                                    {{ format_date($doc->verified_at) }}{{ $doc->verifier ? ' · '.$doc->verifier->name : '' }}
                                </div>
                            @else
                                <x-status-badge severity="neutral" icon="bi-dash-circle" label="Ungeprüft" />
                            @endif
                        </td>
                        @if ($canEdit)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#identity-edit-{{ $doc->id }}" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <x-confirm-form :action="route('persons.identity-documents.destroy', [$entity, $doc])" method="DELETE"
                                                confirm="Dieses Identitätsdokument wirklich löschen? Eine offene Wiedervorlage wird abgebrochen."
                                                icon="bi-trash" label="" />
                            </td>
                        @endif
                    </tr>
                    @if ($canEdit)
                        <tr class="collapse" id="identity-edit-{{ $doc->id }}">
                            <td colspan="7">
                                <form method="POST" action="{{ route('persons.identity-documents.update', [$entity, $doc]) }}">
                                    @csrf
                                    @method('PUT')
                                    @include('persons.tabs.partials.identity-fields', ['doc' => $doc, 'types' => $types])
                                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Aktualisieren</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
