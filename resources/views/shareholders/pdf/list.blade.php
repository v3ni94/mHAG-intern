{{-- Aktionärsliste als PDF (Abschnitt 82): CI-Layout von pdf.layout (Agent D) --}}
@extends('pdf.layout')

@section('title', 'Aktionärsliste '.$documentNumber)

@section('content')
    <h1 style="font-size: 16pt; margin-bottom: 2pt;">Aktionärsliste</h1>
    <div style="width: 60pt; height: 3pt; background: #E3AC48; margin-bottom: 14pt;"></div>

    <table style="width: 100%; font-size: 9pt; margin-bottom: 16pt; border-collapse: collapse;">
        <tr>
            <td style="width: 35%; color: #9F9F9F; padding: 2pt 0;">Gesellschaft</td>
            <td style="padding: 2pt 0;">{{ $data['company']['name'] }}</td>
        </tr>
        @if (!empty($data['company']['address']))
            <tr>
                <td style="color: #9F9F9F; padding: 2pt 0;">Sitz</td>
                <td style="padding: 2pt 0;">{{ $data['company']['address'] }}</td>
            </tr>
        @endif
        @if (!empty($data['company']['register']))
            <tr>
                <td style="color: #9F9F9F; padding: 2pt 0;">Registereintrag</td>
                <td style="padding: 2pt 0;">{{ $data['company']['register'] }}@if (!empty($data['company']['register_court'])), {{ $data['company']['register_court'] }}@endif</td>
            </tr>
        @endif
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Grundkapital</td>
            <td style="padding: 2pt 0;">{{ format_money($data['base_capital']) }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Gesamtzahl Aktien</td>
            <td style="padding: 2pt 0;">{{ number_format($data['total_shares'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Stichtag</td>
            <td style="padding: 2pt 0;">{{ format_date($data['as_of_date']) }}</td>
        </tr>
        <tr>
            <td style="color: #9F9F9F; padding: 2pt 0;">Dokumentnummer</td>
            <td style="padding: 2pt 0;">{{ $documentNumber }}</td>
        </tr>
    </table>

    <table style="width: 100%; font-size: 9pt; border-collapse: collapse; margin-bottom: 24pt;">
        <thead>
        <tr>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Aktionär</th>
            <th style="text-align: left; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Anschrift</th>
            <th style="text-align: right; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Aktienanzahl</th>
            <th style="text-align: right; border-bottom: 1.5pt solid #2E2D2E; padding: 4pt 2pt;">Anteil</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($data['shareholders'] as $row)
            <tr>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 4pt 2pt;">{{ $row['name'] }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 4pt 2pt;">{{ $row['address'] ?: 'nicht erfasst' }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 4pt 2pt; text-align: right;">{{ number_format($row['shares'], 0, ',', '.') }}</td>
                <td style="border-bottom: 0.5pt solid #DDDBD6; padding: 4pt 2pt; text-align: right;">{{ format_percent($row['percentage']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="padding: 8pt 2pt; color: #9F9F9F;">Zum Stichtag bestehen keine Aktienbestände.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    {{-- Unterschriftsbereiche (Abschnitt 82) --}}
    <table style="width: 100%; font-size: 9pt; margin-top: 40pt; border-collapse: collapse;">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="font-weight: bold; margin-bottom: 28pt;">Vorstand</div>
                <div style="border-top: 0.75pt solid #2E2D2E; padding-top: 3pt;">Name</div>
                <div style="border-top: 0.75pt solid #2E2D2E; margin-top: 24pt; padding-top: 3pt;">Datum</div>
                <div style="border-top: 0.75pt solid #2E2D2E; margin-top: 24pt; padding-top: 3pt;">Unterschrift</div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="font-weight: bold; margin-bottom: 28pt;">Aufsichtsratsvorsitzender</div>
                <div style="border-top: 0.75pt solid #2E2D2E; padding-top: 3pt;">Name</div>
                <div style="border-top: 0.75pt solid #2E2D2E; margin-top: 24pt; padding-top: 3pt;">Datum</div>
                <div style="border-top: 0.75pt solid #2E2D2E; margin-top: 24pt; padding-top: 3pt;">Unterschrift</div>
            </td>
        </tr>
    </table>
@endsection
