@extends('layouts.app')

@section('title', 'Vorlage bearbeiten')

@section('content')
    <x-page-header :title="'Vorlage bearbeiten: '.$template->name" label="Vertragsvorlage" />

    <div class="alert alert-info">
        Hier werden nur die Stammdaten der Vorlage geändert. Der Vorlagentext wird ausschließlich
        über neue Versionen angepasst (siehe Detailseite), damit bestehende Verträge unverändert bleiben.
    </div>

    <form method="POST" action="{{ route('contract-templates.update', $template) }}" class="card" style="max-width: 720px;">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $template->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="category">Kategorie</label>
                <input type="text" name="category" id="category" value="{{ old('category', $template->category) }}" class="form-control @error('category') is-invalid @enderror">
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Beschreibung</label>
                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $template->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input" @checked(old('is_active', $template->is_active))>
                <label class="form-check-label" for="is_active">Vorlage ist aktiv (für neue Verträge verfügbar)</label>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
            <a href="{{ route('contract-templates.show', $template) }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>
@endsection
