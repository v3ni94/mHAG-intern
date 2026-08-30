@extends('layouts.app')

@section('title', 'Changelog verwalten')

@section('content')
    <x-page-header title="Changelog verwalten" label="Administration">
        <a href="{{ route('help.changelog') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Ansicht "Was ist neu?"</a>
        <a href="{{ route('admin.changelog.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Neuer Eintrag</a>
    </x-page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Datum</th>
                        <th>Änderungen</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="fw-semibold">{{ $entry->version }}</td>
                            <td class="text-nowrap">{{ format_date($entry->released_on) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $entry->changes), 120) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.changelog.edit', $entry) }}" class="btn btn-sm btn-outline-secondary" aria-label="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <x-confirm-form :action="route('admin.changelog.destroy', $entry)" method="DELETE"
                                                confirm="Changelog-Eintrag wirklich löschen?"
                                                label="Löschen" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="bi-stars" message="Noch keine Changelog-Einträge vorhanden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
@endsection
