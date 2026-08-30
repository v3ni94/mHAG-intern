@foreach (['success' => 'success', 'warning' => 'warning', 'danger' => 'danger', 'info' => 'info', 'error' => 'danger'] as $key => $class)
    @if (session($key))
        <div class="alert alert-{{ $class }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    @endif
@endforeach

@if ($errors->any() && ! request()->routeIs('login', 'two-factor.*'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Bitte prüfen Sie Ihre Eingaben:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    </div>
@endif
