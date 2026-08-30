{{-- Beschluss-PDF (Abschnitt 97): CI-Layout von pdf.layout (Agent D) --}}
@extends('pdf.layout')

@section('title', 'Beschluss '.$resolution->resolution_number)

@section('content')
    <h1 style="font-size: 16pt; margin-bottom: 2pt;">
        {{ $resolution->type?->label() }}
        @if (!empty($preview))
            <span style="font-size: 9pt; color: #B3261E;">(Vorschau, nicht finalisiert)</span>
        @endif
    </h1>
    <div style="width: 60pt; height: 3pt; background: #E3AC48; margin-bottom: 14pt;"></div>

    <table style="width: 100%; font-size: 9pt; margin-bottom: 14pt; border-collapse: collapse;">
        <tr>
            <td style="width: 35%; color: #9F9F9F; padding: 2pt 0;">Gesellschaft</td>
            <td style="padding: 2pt 0;">{{ $resolution->company?->display_name }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Beschlussnummer</td>
            <td style="padding: 2pt 0;">{{ $resolution->resolution_number }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Beschlussart</td>
            <td style="padding: 2pt 0;">{{ $resolution->type?->label() }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Titel</td>
            <td style="padding: 2pt 0;">{{ $resolution->title }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Tatsächliches Beschlussdatum</td>
            <td style="padding: 2pt 0;">{{ format_date($resolution->resolved_on) ?: 'nicht erfasst' }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Erfasst am</td>
            <td style="padding: 2pt 0;">{{ format_datetime($resolution->recorded_at) }}</td>
        </tr>
        @if ($resolution->applicant)
            <tr>
                <td style="color: #9F9F9F; padding: 2pt 0;">Antragsteller</td>
                <td style="padding: 2pt 0;">{{ $resolution->applicant->display_name }}</td>
            </tr>
        @endif
    </table>

    <h2 style="font-size: 11pt; margin-bottom: 4pt;">Teilnehmer und Abstimmung</h2>
    <table style="width: 100%; font-size: 9pt; border-collapse: collapse; margin-bottom: 14pt;">
        <thead>
        <tr>
            <th style="text-align: left; border-bottom: 1pt solid #2E2D2E; padding: 3pt 2pt;">Teilnehmer</th>
            <th style="text-align: left; border-bottom: 1pt solid #2E2D2E; padding: 3pt 2pt;">Rolle</th>
            <th style="text-align: left; border-bottom: 1pt solid #2E2D2E; padding: 3pt 2pt;">Stimme</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($resolution->participants as $participant)
            <tr>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ $participant->entity?->display_name }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ $participant->role }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">
                    {{ $participant->vote?->vote?->label() ?? 'Keine Angabe' }}
                    @if ($participant->excluded_from_vote) (von der Abstimmung ausgeschlossen) @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="3" style="padding: 4pt 2pt; color: #9F9F9F;">Keine Teilnehmer erfasst.</td></tr>
        @endforelse
        </tbody>
    </table>

    <p style="font-size: 9pt; margin-bottom: 14pt;">
        Stimmen: Ja {{ $summary['yes'] }}, Nein {{ $summary['no'] }}, Enthaltung {{ $summary['abstain'] }},
        nicht teilgenommen {{ $summary['absent'] }}.
        Ergebnis:
        @switch($resolution->result)
            @case('accepted') angenommen @break
            @case('rejected') abgelehnt @break
            @case('postponed') vertagt @break
            @case('withdrawn') zurückgezogen @break
            @default offen
        @endswitch
    </p>

    @if ($resolution->motion)
        <h2 style="font-size: 11pt; margin-bottom: 4pt;">Antrag</h2>
        <p style="font-size: 9.5pt; white-space: pre-wrap; margin-bottom: 12pt;">{{ $resolution->motion }}</p>
    @endif

    @if ($resolution->reasoning)
        <h2 style="font-size: 11pt; margin-bottom: 4pt;">Begründung</h2>
        <p style="font-size: 9.5pt; white-space: pre-wrap; margin-bottom: 12pt;">{{ $resolution->reasoning }}</p>
    @endif

    @if ($resolution->resolution_text)
        <h2 style="font-size: 11pt; margin-bottom: 4pt;">Beschlusstext</h2>
        <p style="font-size: 9.5pt; white-space: pre-wrap; margin-bottom: 12pt;">{{ $resolution->resolution_text }}</p>
    @endif

    @if ($resolution->conflict_of_interest)
        <h2 style="font-size: 11pt; margin-bottom: 4pt;">Interessenkonflikt</h2>
        <p style="font-size: 9.5pt; white-space: pre-wrap; margin-bottom: 12pt;">{{ $resolution->conflict_notes }}</p>
    @endif

    @if ($resolution->links->isNotEmpty())
        <h2 style="font-size: 11pt; margin-bottom: 4pt;">Anlagen und Verknüpfungen</h2>
        <ul style="font-size: 9.5pt; margin-bottom: 12pt;">
            @foreach ($resolution->links as $link)
                <li>{{ class_basename($link->linkable_type) }} #{{ $link->linkable_id }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Unterschriftsfelder --}}
    <table style="width: 100%; font-size: 9pt; margin-top: 36pt; border-collapse: collapse;">
        <tr>
            @foreach ($resolution->participants->take(2) as $participant)
                <td style="width: 48%; vertical-align: top; {{ !$loop->first ? 'padding-left: 12pt;' : '' }}">
                    <div style="border-top: 0.75pt solid #2E2D2E; margin-top: 40pt; padding-top: 3pt;">
                        {{ $participant->entity?->display_name }}<br>
                        <span style="color: #9F9F9F;">{{ $participant->role }} · Ort, Datum, Unterschrift</span>
                    </div>
                </td>
                @if ($loop->first && ! $loop->last)<td style="width: 4%;"></td>@endif
            @endforeach
            @if ($resolution->participants->isEmpty())
                <td style="width: 48%;">
                    <div style="border-top: 0.75pt solid #2E2D2E; margin-top: 40pt; padding-top: 3pt;">
                        <span style="color: #9F9F9F;">Ort, Datum, Unterschrift</span>
                    </div>
                </td>
            @endif
        </tr>
    </table>
@endsection
