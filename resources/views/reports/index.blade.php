@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <x-page-header title="Reports" label="Auswertungen" />

    <div class="row g-3">
        @foreach ($reports as $key => $definition)
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('reports.show', $key) }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body d-flex gap-3">
                            <div class="fs-3 text-secondary"><i class="bi {{ $definition[2] }}"></i></div>
                            <div>
                                <div class="fw-semibold text-body">{{ $definition[0] }}</div>
                                <div class="text-muted small">{{ $definition[1] }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <p class="text-muted small mt-4">
        Jeder Report kann als PDF, XLSX oder CSV exportiert werden. Gesetzte Filter werden in den Export übernommen.
    </p>
@endsection
