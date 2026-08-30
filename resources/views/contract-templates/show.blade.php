@extends('layouts.app')

@section('title', 'Vorlage '.$template->name)

@section('content')
    <x-page-header :title="$template->name" label="Vertragsvorlage">
        @if ($template->is_active)
            <x-status-badge severity="success" label="Aktiv" />
        @else
            <x-status-badge severity="neutral" label="Inaktiv" />
        @endif
        @can('contracts.update')
            <a href="{{ route('contract-templates.edit', $template) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil"></i> Stammdaten bearbeiten
            </a>
        @endcan
        @can('contracts.create')
            <a href="{{ route('contracts.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-plus"></i> Vertrag erstellen
            </a>
        @endcan
    </x-page-header>

    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card mb-3">
                <div class="card-header">Stammdaten</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th class="w-25">Kategorie</th><td>{{ $template->category ?: '–' }}</td></tr>
                        <tr><th>Beschreibung</th><td>{{ $template->description ?: '–' }}</td></tr>
                        <tr><th>Angelegt am</th><td>{{ format_datetime($template->created_at) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Standardplatzhalter</div>
                <div class="card-body">
                    <ul class="small mb-0">
                        @foreach ($placeholders as $placeholder)
                            <li><code>&#123;&#123;{{ $placeholder }}&#125;&#125;</code></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-3">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Versionen</span>
                    <x-help-icon text="Vorlagentexte werden nie geändert. Jede Anpassung erzeugt eine neue Version; bestehende Verträge bleiben unverändert (Snapshot-Prinzip)." />
                </div>
                <div class="card-body p-0">
                    @if ($template->versions->isEmpty())
                        <x-empty-state icon="bi-file-earmark-ruled" message="Noch keine Version hinterlegt." />
                    @else
                        <div class="accordion accordion-flush" id="versionen">
                            @foreach ($template->versions as $version)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#version-{{ $version->id }}">
                                            <span class="fw-bold me-2">Version {{ $version->version }}</span>
                                            <span class="text-muted small">
                                                {{ format_datetime($version->created_at) }}
                                                @if ($version->creator) · {{ $version->creator->name }} @endif
                                                @if ($loop->first) · aktuellste Version @endif
                                            </span>
                                        </button>
                                    </h2>
                                    <div id="version-{{ $version->id }}" class="accordion-collapse collapse" data-bs-parent="#versionen">
                                        <div class="accordion-body">
                                            @if (! empty($version->placeholders))
                                                <div class="mb-2 small">
                                                    <span class="text-muted">Enthaltene Platzhalter:</span>
                                                    @foreach ($version->placeholders as $ph)
                                                        <code class="me-1">&#123;&#123;{{ $ph }}&#125;&#125;</code>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <pre class="small bg-light border rounded p-2 mb-0" style="max-height: 320px; overflow: auto;">{{ $version->body }}</pre>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @can('contracts.update')
                <form method="POST" action="{{ route('contract-templates.versions.store', $template) }}" class="card">
                    @csrf
                    <div class="card-header">Neue Version anlegen</div>
                    <div class="card-body">
                        <div class="mb-3" style="max-width: 220px;">
                            <label class="form-label" for="version">Versionsbezeichnung <span class="text-danger">*</span></label>
                            <input type="text" name="version" id="version" value="{{ old('version') }}" class="form-control @error('version') is-invalid @enderror" placeholder="z. B. 1.1" required>
                            @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="body">Vorlagentext (HTML mit Platzhaltern) <span class="text-danger">*</span></label>
                            <textarea name="body" id="body" rows="14" class="form-control font-monospace @error('body') is-invalid @enderror" required>{{ old('body', $template->versions->first()?->body) }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Neue Version speichern</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
@endsection
