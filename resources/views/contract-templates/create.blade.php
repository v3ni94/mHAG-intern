@extends('layouts.app')

@section('title', 'Neue Vertragsvorlage')

@section('content')
    <x-page-header title="Neue Vertragsvorlage" label="Vertragsverwaltung" />

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('contract-templates.store') }}" class="card">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="category">Kategorie</label>
                            <input type="text" name="category" id="category" value="{{ old('category') }}" class="form-control @error('category') is-invalid @enderror" placeholder="z. B. Privatdarlehen">
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="version">Erste Version</label>
                            <input type="text" name="version" id="version" value="{{ old('version', '1.0') }}" class="form-control @error('version') is-invalid @enderror">
                            @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Beschreibung</label>
                        <textarea name="description" id="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="body">Vorlagentext (HTML mit Platzhaltern) <span class="text-danger">*</span></label>
                        <textarea name="body" id="body" rows="18" class="form-control font-monospace @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Vorlage anlegen</button>
                    <a href="{{ route('contract-templates.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Verfügbare Standardplatzhalter</div>
                <div class="card-body">
                    <p class="small text-muted">Platzhalter im Format <code>@{{platzhalter}}</code> werden bei der Vertragserstellung automatisch aus dem Darlehen befüllt:</p>
                    <ul class="small mb-0">
                        @foreach ($placeholders as $placeholder)
                            <li><code>&#123;&#123;{{ $placeholder }}&#125;&#125;</code></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
