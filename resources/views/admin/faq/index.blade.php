@extends('layouts.app')

@section('title', 'FAQ-Verwaltung')

@section('content')
    <x-page-header title="FAQ-Verwaltung" label="Administration">
        <a href="{{ route('admin.faq.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Neuer Eintrag</a>
    </x-page-header>

    <form method="GET" class="mb-3">
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <select name="kategorie" class="form-select" aria-label="Kategorie">
                <option value="">Alle Kategorien</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('kategorie') === $category)>{{ $category }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary">Filtern</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="num">Sortierung</th>
                        <th>Kategorie</th>
                        <th>Frage</th>
                        <th>Sichtbarkeit</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="num">{{ $entry->sort }}</td>
                            <td>{{ $entry->category ?: 'Allgemein' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($entry->question, 90) }}</td>
                            <td>
                                @php($visibilityLabels = ['all' => 'Alle', 'internal' => 'Intern', 'admin' => 'Administratoren', 'supervisory_board' => 'Aufsichtsrat', 'lender' => 'Darlehensgeber', 'borrower' => 'Darlehensnehmer'])
                                <x-status-badge :severity="$entry->visibility === 'all' ? 'info' : 'neutral'" :label="$visibilityLabels[$entry->visibility] ?? $entry->visibility" />
                            </td>
                            <td>
                                @if ($entry->is_active)
                                    <x-status-badge severity="success" label="Aktiv" />
                                @else
                                    <x-status-badge severity="neutral" label="Inaktiv" />
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.faq.edit', $entry) }}" class="btn btn-sm btn-outline-secondary" aria-label="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <x-confirm-form :action="route('admin.faq.destroy', $entry)" method="DELETE"
                                                confirm="FAQ-Eintrag wirklich löschen?"
                                                label="Löschen" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-question-circle" message="Keine FAQ-Einträge vorhanden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
@endsection
