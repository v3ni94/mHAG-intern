@extends('layouts.app')

@section('title', 'Personen')

@section('content')
    <x-page-header title="Personen" label="Stammdaten">
        @can('persons.create')
            <a href="{{ route('persons.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Neue Person
            </a>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('persons.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-5 col-lg-4">
            <label for="filter-q" class="form-label versal-label">Suche</label>
            <input type="search" id="filter-q" name="q" value="{{ $q }}" class="form-control form-control-sm"
                   placeholder="Name, Geburtsname, Personen-Nr.">
        </div>
        <div class="col-md-3 col-lg-2">
            <label for="filter-status" class="form-label versal-label">Status</label>
            <select id="filter-status" name="status" class="form-select form-select-sm">
                <option value="active" @selected($status === 'active')>Aktiv</option>
                <option value="archived" @selected($status === 'archived')>Archiviert</option>
                <option value="all" @selected($status === 'all')>Alle</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filtern</button>
        </div>
    </form>

    @if ($entities->isEmpty())
        <x-empty-state icon="bi-person" message="Keine Personen gefunden.">
            @can('persons.create')
                <a href="{{ route('persons.create') }}" class="btn btn-sm btn-outline-secondary">Erste Person anlegen</a>
            @endcan
        </x-empty-state>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Personen-Nr.</th>
                        <th>Geburtsdatum</th>
                        <th>Ort</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($entities as $entity)
                        <tr>
                            <td>
                                <a href="{{ route('persons.show', $entity) }}" class="fw-semibold text-decoration-none">
                                    {{ $entity->display_name }}
                                </a>
                                @if ($entity->person?->birth_name)
                                    <div class="text-muted small">geb. {{ $entity->person->birth_name }}</div>
                                @endif
                            </td>
                            <td>{{ $entity->internal_number }}</td>
                            <td>{{ format_date($entity->person?->date_of_birth) }}</td>
                            <td>{{ $entity->primaryAddress()?->city }}</td>
                            <td>{{ $entity->primaryEmail() }}</td>
                            <td>@include('persons.partials.entity-status', ['entity' => $entity])</td>
                            <td class="text-end">
                                <a href="{{ route('persons.show', $entity) }}" class="btn btn-sm btn-outline-secondary" title="Akte öffnen">
                                    <i class="bi bi-folder2-open"></i>
                                </a>
                                @can('persons.update')
                                    <a href="{{ route('persons.edit', $entity) }}" class="btn btn-sm btn-outline-secondary" title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $entities->links() }}</div>
    @endif
@endsection
