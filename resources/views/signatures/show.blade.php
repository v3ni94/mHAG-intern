@extends('layouts.app')
@section('title', 'Signaturanfrage #'.$request->id)
@section('content')
    <x-page-header :title="'Signaturanfrage #'.$request->id" label="Signaturen">
        <a href="{{ route('signatures.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
        @can('resolutions.sign')
            @php($istDocuSign = $request->provider === 'docusign')
            @if ($request->status?->value === 'draft')
                <x-confirm-form :action="route('signatures.send', $request)"
                                :confirm="$istDocuSign
                                    ? 'Umschlag jetzt bei DocuSign erzeugen und an die Unterzeichner versenden?'
                                    : 'Anfrage als versendet markieren? Der Versand erfolgt beim manuellen Prozess außerhalb des Systems.'"
                                label="Versenden" icon="bi-send" class="btn btn-primary btn-sm" />
            @endif
            @if ($request->status?->value !== 'draft' && $request->status?->value !== 'completed')
                {{-- Status beim Anbieter abfragen (Abschnitt 102) --}}
                <form method="POST" action="{{ route('signatures.sync', $request) }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-repeat"></i>
                        {{ $istDocuSign ? 'Status bei DocuSign abfragen' : 'Status aktualisieren' }}
                    </button>
                </form>
            @endif
        @endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Anfrage</span>
                    <x-enum-badge :enum="$request->status" />
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Signaturweg</dt>
                        <dd class="col-sm-7">
                            {{ $request->provider === 'docusign' ? 'DocuSign' : 'Manuell' }}
                            @if ($request->external_id)
                                <div class="small text-muted">Umschlag: {{ $request->external_id }}</div>
                            @endif
                        </dd>
                        <dt class="col-sm-5">Vorgang</dt>
                        <dd class="col-sm-7">
                            {{ class_basename($request->subject_type) }}
                            @if ($request->subject)
                                · {{ $request->subject->resolution_number
                                    ?? $request->subject->contract_number
                                    ?? $request->subject->transaction_number
                                    ?? $request->subject->document_number
                                    ?? ('#'.$request->subject_id) }}
                            @endif
                        </dd>
                        <dt class="col-sm-5">Anbieter</dt>
                        <dd class="col-sm-7">{{ $request->provider === 'manual' ? 'Manueller Prozess' : $request->provider }}</dd>
                        <dt class="col-sm-5">Dokument</dt>
                        <dd class="col-sm-7">
                            @if ($request->document)
                                <i class="bi bi-file-earmark-pdf me-1"></i>{{ $request->document->original_filename }}
                                <div class="text-muted small font-monospace">SHA-256: {{ substr((string) $request->document->sha256, 0, 24) }}...</div>
                            @else
                                <span class="text-muted">keines</span>
                            @endif
                        </dd>
                        <dt class="col-sm-5">Erstellt von</dt>
                        <dd class="col-sm-7">{{ $request->creator?->name ?? 'System' }}</dd>
                        <dt class="col-sm-5">Erstellt am</dt>
                        <dd class="col-sm-7">{{ format_datetime($request->created_at) }}</dd>
                    </dl>
                </div>
            </div>

            @can('resolutions.sign')
                @if ($request->status?->value !== 'completed')
                    <div class="card">
                        <div class="card-header">Signierte Fassung hochladen</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('signatures.attach-signed', $request) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-2">
                                    <input type="file" name="signed_file" accept="application/pdf"
                                           class="form-control form-control-sm @error('signed_file') is-invalid @enderror" required>
                                    @error('signed_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-primary btn-sm"
                                        onclick="return confirm('Signierte Fassung übernehmen und den Vorgang abschließen?');">
                                    <i class="bi bi-upload"></i> Übernehmen und abschließen
                                </button>
                                <div class="form-text">
                                    Schließt die Anfrage ab und schreibt den Status des Vorgangs fort
                                    (z. B. Beschluss auf "unterschrieben").
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-1"></i> Die Anfrage ist abgeschlossen; die signierte Fassung ist hinterlegt.
                    </div>
                @endif
            @endcan
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Unterzeichner und Status (Abschnitt 102)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                            <tr>
                                <th>Unterzeichner</th>
                                <th>Rolle</th>
                                <th>E-Mail</th>
                                <th>Status</th>
                                <th>Geändert am</th>
                                @can('resolutions.sign')<th></th>@endcan
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($request->participants as $participant)
                                <tr>
                                    <td>{{ $participant->entity?->display_name }}</td>
                                    <td class="small">{{ $participant->role }}</td>
                                    <td class="small">{{ $participant->email }}</td>
                                    <td><x-enum-badge :enum="$participant->status" /></td>
                                    <td class="small">{{ format_datetime($participant->status_changed_at) }}</td>
                                    @can('resolutions.sign')
                                        <td style="min-width: 220px;">
                                            @if ($request->status?->value !== 'completed')
                                                <form method="POST" action="{{ route('signatures.mark', $request) }}" class="d-flex gap-1">
                                                    @csrf
                                                    <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                                    <select name="status" class="form-select form-select-sm">
                                                        @foreach ($participantStatuses as $status)
                                                            @continue($status->value === 'not_sent')
                                                            <option value="{{ $status->value }}" @selected($participant->status?->value === $status->value)>
                                                                {{ $status->label() }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button class="btn btn-outline-secondary btn-sm">Setzen</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">Keine Unterzeichner erfasst.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Manueller Prozess: Die Status werden hier von Hand gepflegt; ein externer Signaturanbieter ist nicht angebunden.
                </div>
            </div>
        </div>
    </div>
@endsection
