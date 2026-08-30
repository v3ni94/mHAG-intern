@extends('layouts.app')

@section('title', 'Wussten Sie? verwalten')

@section('content')
    <x-page-header title='Einträge "Wussten Sie?"' label="Administration">
        <a href="{{ route('admin.daily-facts.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Neuer Eintrag</a>
    </x-page-header>

    <div class="alert alert-info small">
        Die Einträge erscheinen in der Fußzeile, wenn Monat und Tag dem heutigen Datum entsprechen
        oder das hinterlegte Datum heute ist. Ohne passenden aktiven Eintrag zeigt die Fußzeile nichts an.
        Die Quelle ist Pflichtfeld, damit keine unbelegten Angaben erscheinen.
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <select name="monat" class="form-select" aria-label="Monat">
                <option value="">Alle Monate</option>
                @foreach (['01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April', '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('monat') === $value)>{{ $label }}</option>
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
                        <th>Tag</th>
                        <th>Titel</th>
                        <th>Quelle</th>
                        <th>Land</th>
                        <th>Art</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="text-nowrap">
                                @php([$month, $day] = array_pad(explode('-', (string) $entry->month_day, 2), 2, ''))
                                {{ $entry->recurring ? $day.'.'.$month.'.' : format_date($entry->specific_date) }}
                                @if ($entry->month_day === $today)
                                    <span class="badge text-bg-light">heute</span>
                                @endif
                            </td>
                            <td>{{ $entry->title }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($entry->source, 60) }}</td>
                            <td class="small">{{ $entry->country }}</td>
                            <td>
                                <x-status-badge :severity="$entry->recurring ? 'info' : 'neutral'"
                                                :label="$entry->recurring ? 'Jährlich' : 'Einmalig'" />
                            </td>
                            <td>
                                @if ($entry->is_active)
                                    <x-status-badge severity="success" label="Aktiv" />
                                @else
                                    <x-status-badge severity="neutral" label="Inaktiv" />
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.daily-facts.edit', $entry) }}" class="btn btn-sm btn-outline-secondary" aria-label="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <x-confirm-form :action="route('admin.daily-facts.destroy', $entry)" method="DELETE"
                                                confirm="Eintrag wirklich löschen?"
                                                label="Löschen" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-lightbulb" message="Noch keine Einträge vorhanden. Die Fußzeile bleibt bis zum ersten gepflegten Eintrag leer." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
@endsection
