@extends('layouts.app')

@section('title', 'Dokument hochladen')

@section('content')
    <x-page-header title="Dokument hochladen" label="Dokumentenmanagement" />

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="file">Datei <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Zulässig sind unter anderem PDF, Bilder und Office-Dokumente, maximal
                            {{ number_format((int) (config('documents.max_size_kb') / 1024), 0, ',', '.') }} MB.
                            Ausführbare Dateien werden abgelehnt.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="doc_type">Dokumenttyp <span class="text-danger">*</span></label>
                            <select name="doc_type" id="doc_type" class="form-select @error('doc_type') is-invalid @enderror" required>
                                <option value="">Bitte wählen ...</option>
                                @foreach ($docTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('doc_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('doc_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="category">Kategorie</label>
                            <input type="text" name="category" id="category" value="{{ old('category') }}" class="form-control @error('category') is-invalid @enderror">
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="document_date">Dokumentdatum</label>
                            <input type="date" name="document_date" id="document_date" value="{{ old('document_date') }}" class="form-control @error('document_date') is-invalid @enderror">
                            @error('document_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="expires_on">
                                Ablaufdatum
                                <x-help-icon text="Bei gesetztem Ablaufdatum wird automatisch eine Wiedervorlage angelegt." />
                            </label>
                            <input type="date" name="expires_on" id="expires_on" value="{{ old('expires_on') }}" class="form-control @error('expires_on') is-invalid @enderror">
                            @error('expires_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Beschreibung</label>
                        <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="tags">Tags (durch Komma getrennt)</label>
                        <input type="text" name="tags" id="tags" value="{{ old('tags') }}" class="form-control @error('tags') is-invalid @enderror" placeholder="z. B. 2026, Original, geprüft">
                        @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <hr>
                    <h2 class="h6">Verknüpfung (optional)</h2>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="link_type">Verknüpfungsart</label>
                            <select name="link_type" id="link_type" class="form-select @error('link_type') is-invalid @enderror">
                                <option value="">Keine Verknüpfung</option>
                                @foreach ($linkableLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('link_type', $preselectedType) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('link_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 mb-3" id="link-loan-select">
                            <label class="form-label" for="link_id_loan">Darlehen</label>
                            <select id="link_id_loan" class="form-select" data-link-select="loan">
                                <option value="">Bitte wählen ...</option>
                                @foreach ($loans as $loan)
                                    <option value="{{ $loan->id }}">{{ $loan->loan_number }} · {{ $loan->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="link-entity-select">
                            <label class="form-label" for="link_id_entity">Person / Unternehmen</label>
                            <select id="link_id_entity" class="form-select" data-link-select="entity">
                                <option value="">Bitte wählen ...</option>
                                @foreach ($entities as $entity)
                                    <option value="{{ $entity->id }}">{{ $entity->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="link_id">
                                ID des Verknüpfungsziels
                                <x-help-icon text="Wird bei Auswahl über die Listen automatisch gefüllt. Für andere Verknüpfungsarten die Datensatz-ID eintragen." />
                            </label>
                            <input type="number" min="1" name="link_id" id="link_id" value="{{ old('link_id', $preselectedId) }}" class="form-control @error('link_id') is-invalid @enderror">
                            @error('link_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Hochladen</button>
                    <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-shield-check me-1"></i>Sichere Ablage</h2>
                    <p class="small text-muted mb-0">
                        Jede Datei wird beim Upload geprüft (Dateityp, Endung, Größe), mit einer
                        SHA-256-Prüfsumme versehen und erst nach erfolgreicher Verifizierung der
                        Übertragung gespeichert. Downloads erfolgen ausschließlich über
                        berechtigungsgeprüfte Serverabrufe.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const typeSelect = document.getElementById('link_type');
        const idInput = document.getElementById('link_id');
        const loanWrap = document.getElementById('link-loan-select');
        const entityWrap = document.getElementById('link-entity-select');

        function refresh() {
            const t = typeSelect.value;
            loanWrap.style.display = t === 'loan' ? '' : 'none';
            entityWrap.style.display = t === 'entity' ? '' : 'none';
        }

        document.querySelectorAll('[data-link-select]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.value) { idInput.value = select.value; }
            });
        });

        typeSelect.addEventListener('change', refresh);
        refresh();
    })();
</script>
@endpush
