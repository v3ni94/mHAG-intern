@extends('pdf.layout')

@section('title', $contract->title)

@section('pdf-styles')
    .draft-mark {
        position: fixed;
        top: 40%;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 96px;
        color: #F4E3E2;
        letter-spacing: 18px;
        z-index: -1;
    }
@endsection

@section('content')
    @if (! empty($isDraft) || $contract->status === 'draft')
        <div class="draft-mark">ENTWURF</div>
        <p class="text-muted" style="font-size: 8px; letter-spacing: 2px; text-transform: uppercase;">
            Entwurf, noch nicht finalisiert. Keine rechtsverbindliche Fassung.
        </p>
    @endif

    {!! $contract->body_snapshot !!}
@endsection
