@extends('pdf.layout')

@section('title', $title)

@section('content')
    <p style="color: #55534f; font-size: 9px;">
        Erstellt am {{ format_datetime($generatedAt) }}
        @if (array_filter($report['filters'] ?? []))
            · Filter:
            @foreach (array_filter($report['filters']) as $name => $value)
                {{ $name }} = {{ is_bool($value) ? ($value ? 'ja' : 'nein') : $value }}@if (! $loop->last), @endif
            @endforeach
        @endif
    </p>

    @if (! empty($report['hint']))
        <p style="font-size: 9px; color: #55534f;">{{ $report['hint'] }}</p>
    @endif

    <table width="100%" cellpadding="4" cellspacing="0" style="border-collapse: collapse; font-size: 8.5px;">
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th style="border-bottom: 1.5px solid #E3AC48; text-align: left; padding: 4px;">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td style="border-bottom: 0.5px solid #DDDBD6; padding: 3px 4px;">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}" style="padding: 8px; color: #55534f;">Keine Daten für die gewählten Filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
