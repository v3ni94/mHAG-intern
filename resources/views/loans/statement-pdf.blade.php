@extends('pdf.layout')

@section('title', 'Forderungsaufstellung')

@section('content')
    <p class="doc-meta">
        <span class="versal-label">Darlehen <span class="value">{{ $loan->loan_number }}</span></span>
        <span class="versal-label">Darlehensgeber <span class="value">{{ $loan->lender?->display_name }}</span></span>
        <span class="versal-label">Darlehensnehmer <span class="value">{{ $loan->borrower?->display_name }}</span></span>
    </p>

    @if ($loan->title)
        <p>{{ $loan->title }}</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>Position</th>
                <th class="num">Betrag</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['sign'] === '-' ? './.' : '' }} {{ $row['label'] }}</td>
                    <td class="num">{{ format_money($row['amount'], $loan->currency ?? 'EUR') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="fw-bold">Gesamtforderung zum {{ format_date($asOfDate) }}</td>
                <td class="num fw-bold">{{ format_money($total, $loan->currency ?? 'EUR') }}</td>
            </tr>
        </tbody>
    </table>

    <p class="text-muted" style="margin-top: 18px;">
        Diese Aufstellung wurde maschinell zum genannten Stichtag aus den erfassten Vertrags- und Zahlungsdaten erstellt.
        Systemseitig angenommene, nicht bestätigte Zahlungen sind in den zugrunde liegenden Daten entsprechend gekennzeichnet.
        Die Aufstellung stellt keine rechtliche Bewertung dar.
    </p>
@endsection
