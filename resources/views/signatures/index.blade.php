@extends('layouts.app')
@section('title', 'Unterschriften')
@section('content')
    <x-page-header title="Digitale Unterschriften" label="Signaturen">
        @can('resolutions.sign')
            <a href="{{ route('signatures.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Signaturanfrage erstellen
            </a>
        @endcan
    </x-page-header>

    <div class="card mb-4">
        <div class="card-header">Offene Anfragen</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Anfrage</th>
                        <th>Vorgang</th>
                        <th>Anbieter</th>
                        <th>Unterzeichner (Status je Person)</th>
                        <th>Status</th>
                        <th>Erstellt</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($open as $request)
                        <tr>
                            <td><a href="{{ route('signatures.show', $request) }}">#{{ $request->id }}</a></td>
                            <td class="small">
                                {{ class_basename($request->subject_type) }}
                                @if ($request->subject)
                                    · {{ $request->subject->resolution_number
                                        ?? $request->subject->contract_number
                                        ?? $request->subject->transaction_number
                                        ?? $request->subject->document_number
                                        ?? ('#'.$request->subject_id) }}
                                @endif
                            </td>
                            <td class="small">{{ $request->provider === 'manual' ? 'Manuell' : $request->provider }}</td>
                            <td>
                                @foreach ($request->participants as $participant)
                                    <span class="me-2 text-nowrap small">
                                        {{ $participant->entity?->display_name }}
                                        <x-enum-badge :enum="$participant->status" />
                                    </span>
                                @endforeach
                            </td>
                            <td><x-enum-badge :enum="$request->status" /></td>
                            <td class="small">{{ format_date($request->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-pen" message="Keine offenen Signaturanfragen." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Abgeschlossene und beendete Anfragen</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr><th>Anfrage</th><th>Vorgang</th><th>Status</th><th>Aktualisiert</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($closed as $request)
                        <tr>
                            <td><a href="{{ route('signatures.show', $request) }}">#{{ $request->id }}</a></td>
                            <td class="small">
                                {{ class_basename($request->subject_type) }}
                                @if ($request->subject)
                                    · {{ $request->subject->resolution_number
                                        ?? $request->subject->contract_number
                                        ?? $request->subject->transaction_number
                                        ?? $request->subject->document_number
                                        ?? ('#'.$request->subject_id) }}
                                @endif
                            </td>
                            <td><x-enum-badge :enum="$request->status" /></td>
                            <td class="small">{{ format_datetime($request->updated_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Keine abgeschlossenen Anfragen.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($closed->hasPages())
            <div class="card-footer">{{ $closed->links() }}</div>
        @endif
    </div>
@endsection
