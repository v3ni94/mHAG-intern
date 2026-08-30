@extends('layouts.app')
@section('title', 'Signaturanfrage erstellen')
@section('content')
    <x-page-header title="Signaturanfrage erstellen" label="Signaturen">
        <a href="{{ route('signatures.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    @if (! $subject)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            Bitte starten Sie die Signaturanfrage aus dem jeweiligen Vorgang (z. B. Beschluss finalisieren und dort
            "Signaturanfrage erstellen" wählen), damit Vorgang und PDF automatisch übernommen werden.
        </div>
    @else
        <div class="card mb-3">
            <div class="card-header">Vorgang</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Vorgangsart</dt>
                    <dd class="col-sm-9">{{ $subjectTypes[$subjectKey][1] ?? $subjectKey }}</dd>
                    <dt class="col-sm-3">Vorgang</dt>
                    <dd class="col-sm-9">
                        {{ $subject->resolution_number
                            ?? $subject->contract_number
                            ?? $subject->transaction_number
                            ?? $subject->document_number
                            ?? ('#'.$subject->getKey()) }}
                        @if (isset($subject->title)) · {{ $subject->title }} @endif
                    </dd>
                    <dt class="col-sm-3">Zu signierendes PDF</dt>
                    <dd class="col-sm-9">
                        @if ($document)
                            <i class="bi bi-file-earmark-pdf me-1"></i>{{ $document->original_filename }}
                            <span class="text-muted small">(SHA-256: {{ substr((string) $document->sha256, 0, 16) }}...)</span>
                        @else
                            <span class="text-danger">
                                Kein PDF hinterlegt. Bitte zuerst das Dokument erzeugen (z. B. Beschluss finalisieren).
                            </span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Unterzeichner (Abschnitt 101)</div>
            <div class="card-body">
                <form method="POST" action="{{ route('signatures.store') }}">
                    @csrf
                    <input type="hidden" name="subject_type" value="{{ $subjectKey }}">
                    <input type="hidden" name="subject_id" value="{{ $subject->getKey() }}">

                    @php($rows = old('participants', count($prefill) > 0 ? $prefill : [['entity_id' => null, 'role' => null, 'email' => null]]))
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr><th style="width: 35%;">Person / Unternehmen</th><th style="width: 30%;">Rolle</th><th>E-Mail (optional)</th></tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $i => $row)
                                <tr>
                                    <td>
                                        <select name="participants[{{ $i }}][entity_id]" class="form-select form-select-sm">
                                            <option value="">Nicht besetzt</option>
                                            @foreach ($entities as $entity)
                                                <option value="{{ $entity->id }}" @selected(($row['entity_id'] ?? null) == $entity->id)>{{ $entity->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="participants[{{ $i }}][role]" class="form-select form-select-sm">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected(($row['role'] ?? '') === $role)>{{ $role }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="email" name="participants[{{ $i }}][email]" class="form-control form-control-sm"
                                               value="{{ $row['email'] ?? '' }}" placeholder="leer = Stammdaten-E-Mail">
                                    </td>
                                </tr>
                            @endforeach
                            {{-- zwei Leerzeilen für weitere Unterzeichner --}}
                            @for ($i = count($rows); $i < count($rows) + 2; $i++)
                                <tr>
                                    <td>
                                        <select name="participants[{{ $i }}][entity_id]" class="form-select form-select-sm">
                                            <option value="">Nicht besetzt</option>
                                            @foreach ($entities as $entity)
                                                <option value="{{ $entity->id }}">{{ $entity->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="participants[{{ $i }}][role]" class="form-select form-select-sm">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}">{{ $role }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="email" name="participants[{{ $i }}][email]" class="form-control form-control-sm" placeholder="leer = Stammdaten-E-Mail"></td>
                                </tr>
                            @endfor
                            </tbody>
                        </table>
                    </div>

                    <div class="form-check mb-3">
                        <input type="hidden" name="send_immediately" value="0">
                        <input type="checkbox" name="send_immediately" id="send_immediately" value="1" class="form-check-input" checked>
                        <label class="form-check-label" for="send_immediately">Anfrage direkt als versendet markieren</label>
                        <div class="form-text">
                            Aktiver Anbieter: Manueller Prozess. Der Versand des Dokuments erfolgt außerhalb des Systems;
                            die Status werden hier gepflegt.
                        </div>
                    </div>

                    <button class="btn btn-primary" @disabled(! $document)>Signaturanfrage erstellen</button>
                </form>
            </div>
        </div>
    @endif
@endsection
