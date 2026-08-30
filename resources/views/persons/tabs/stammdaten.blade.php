{{-- Personenakte: Stammdaten (Abschnitt 6) --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Stammdaten</span>
        @can('persons.update')
            <a href="{{ route('persons.edit', $entity) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
        @endcan
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Anrede</dt>
            <dd class="col-sm-9">{{ $entity->person?->salutation ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Titel</dt>
            <dd class="col-sm-9">{{ $entity->person?->title ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Vorname</dt>
            <dd class="col-sm-9">{{ $entity->person?->first_name }}</dd>
            <dt class="col-sm-3">Weitere Vornamen</dt>
            <dd class="col-sm-9">{{ $entity->person?->middle_names ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Nachname</dt>
            <dd class="col-sm-9">{{ $entity->person?->last_name }}</dd>
            <dt class="col-sm-3">Geburtsname</dt>
            <dd class="col-sm-9">{{ $entity->person?->birth_name ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Geburtsdatum</dt>
            <dd class="col-sm-9">{{ format_date($entity->person?->date_of_birth) ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Geburtsort</dt>
            <dd class="col-sm-9">{{ $entity->person?->place_of_birth ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Staatsangehörigkeit</dt>
            <dd class="col-sm-9">{{ $entity->person?->nationality ?: 'Nicht erfasst' }}</dd>
            <dt class="col-sm-3">Geschlecht</dt>
            <dd class="col-sm-9">{{ $entity->person?->gender ?: 'Keine Angabe' }}</dd>
            <dt class="col-sm-3">Familienstand</dt>
            <dd class="col-sm-9">{{ $entity->person?->marital_status ?: 'Keine Angabe' }}</dd>
            <dt class="col-sm-3">Interne Personen-ID</dt>
            <dd class="col-sm-9">{{ $entity->internal_number }}</dd>
            <dt class="col-sm-3">Tags</dt>
            <dd class="col-sm-9">
                @if (is_array($entity->tags) && count($entity->tags))
                    @foreach ($entity->tags as $tag)
                        <span class="badge text-bg-light border me-1">{{ $tag }}</span>
                    @endforeach
                @else
                    <span class="text-muted">Keine Tags</span>
                @endif
            </dd>
            <dt class="col-sm-3">Angelegt am</dt>
            <dd class="col-sm-9">{{ format_datetime($entity->created_at) }}</dd>
        </dl>
    </div>
</div>
