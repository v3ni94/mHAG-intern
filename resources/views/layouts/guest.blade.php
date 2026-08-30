<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Anmeldung') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo-mhag.jpg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-page">
    <div class="auth-card shadow">
        <div class="head d-flex align-items-center gap-3">
            <img src="{{ asset('images/logo-mhag.jpg') }}" alt="Müller Holding AG" style="height:52px;">
            <div>
                <div class="fw-bold">Müller Holding AG</div>
                <div class="versal-label">Intranet</div>
            </div>
        </div>
        <div class="body">
            @include('partials.flash')
            @yield('content')
        </div>
    </div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
