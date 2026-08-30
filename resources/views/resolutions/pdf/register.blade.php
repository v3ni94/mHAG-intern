{{-- Beschlussregister-Export (Abschnitt 98) --}}
@extends('pdf.layout')

@section('title', 'Beschlussregister')

@section('content')
    <h1 style="font-size: 16pt; margin-bottom: 2pt;">Beschlussregister</h1>
    <div style="width: 60pt; height: 3pt; background: #E3AC48; margin-bottom: 10pt;"></div>

    <p style="font-size: 8.5pt; color: #9F9F9F; margin-bottom: 12pt;">
        Stand: {{ format_datetime($generatedAt) }}
        @if (array_filter($filters))
            · Filter:
            @if (!empty($filters['year'])) Jahr {{ $filters['year'] }} @endif
            @if (!empty($filters['type'])) · Art {{ \App\Enums\ResolutionType::tryFrom($filters['type'])?->label() }} @endif
            @if (!empty($filters['status'])) · Status {{ \App\Enums\ResolutionStatus::tryFrom($filters['status'])?->label() }} @endif
            @if (!empty($filters['q'])) · Suche "{{ $filters['q'] }}" @endif
        @endif
    </p>

    <table style="width: 100%; font-size: 8.5pt; border-collapse: collapse;">
        <thead>
        <tr>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Nr.</th>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Datum</th>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Art</th>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Titel</th>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Ergebnis</th>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Status</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($resolutions as $r)
            <tr>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ $r->resolution_number }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ format_date($r->resolved_on) ?: 'nicht erfasst' }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ $r->type?->label() }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ $r->title }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">
                    @switch($r->result)
                        @case('accepted') angenommen @break
                        @case('rejected') abgelehnt @break
                        @case('postponed') vertagt @break
                        @case('withdrawn') zurückgezogen @break
                        @default offen
                    @endswitch
                </td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 3pt 2pt;">{{ $r->status?->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding: 8pt 2pt; color: #9F9F9F;">Keine Beschlüsse im gewählten Filter.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
