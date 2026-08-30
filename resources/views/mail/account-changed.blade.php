@extends('mail.layout')
@section('title', $headline)
@section('subtitle', 'Kontoinformation')

@section('content')
    <p style="margin: 0 0 12px;">Guten Tag {{ $name }},</p>
    <p style="margin: 0 0 14px;">
        an Ihrem Benutzerkonto im Intranet der Müller Holding AG wurde folgende Änderung vorgenommen:
    </p>
    <ul style="margin: 0 0 16px; padding-left: 20px; font-size: 14px;">
        @foreach ($changes as $change)
            <li style="margin-bottom: 6px;">{{ $change }}</li>
        @endforeach
    </ul>
    <p style="margin: 0 0 14px; font-size: 14px;">
        Zum Intranet: <a href="{{ $loginUrl }}" style="color: #1D5FA6;">{{ $loginUrl }}</a>
    </p>
    <p style="margin: 0; font-size: 13px; color: #55534f;">
        Sollte Ihnen diese Änderung nicht bekannt sein, wenden Sie sich bitte an die Administration.
    </p>
@endsection
