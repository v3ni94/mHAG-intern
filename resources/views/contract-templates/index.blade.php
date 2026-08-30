@extends('layouts.app')

@section('title', 'Vertragsvorlagen')

@section('content')
    <x-page-header title="Vertragsvorlagen" label="Vertragsverwaltung">
        <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-text"></i> Zu den Verträgen
        </a>
        @can('contracts.update')
            <a href="{{ route('contract-templates.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Neue Vorlage
            </a>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('contract-templates.index') }}" class="mb-3 d-flex gap-2" style="max-width: 480px;">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name oder Kategorie suchen ...">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
    </form>

    <div class="card">
        <div class="card-body p-0">
            @if ($templates->isEmpty())
                <x-empty-state icon="bi-file-earmark-ruled" message="Keine Vertragsvorlagen vorhanden." />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Kategorie</th>
                            <th>Versionen</th>
                            <th>Status</th>
                            <th>Beschreibung</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($templates as $template)
                            <tr>
                                <td>
                                    <a href="{{ route('contract-templates.show', $template) }}" class="fw-bold text-decoration-none">
                                        {{ $template->name }}
                                    </a>
                                </td>
                                <td>{{ $template->category }}</td>
                                <td>{{ $template->versions_count }}</td>
                                <td>
                                    @if ($template->is_active)
                                        <x-status-badge severity="success" label="Aktiv" />
                                    @else
                                        <x-status-badge severity="neutral" label="Inaktiv" />
                                    @endif
                                </td>
                                <td class="text-muted small">{{ \Illuminate\Support\Str::limit($template->description, 100) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-3">{{ $templates->links() }}</div>
@endsection
