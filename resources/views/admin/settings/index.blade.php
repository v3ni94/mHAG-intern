@extends('layouts.app')

@section('title', 'Einstellungen')

@section('content')
    <x-page-header title="Systemeinstellungen" label="Administration" />

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header">Zwei-Faktor-Authentifizierung</div>
            <div class="card-body">
                <p class="text-muted small">Rollen, für die die Zwei-Faktor-Authentifizierung verpflichtend ist.</p>
                @error('two_factor_required_roles')<div class="text-danger small">{{ $message }}</div>@enderror
                <div class="row">
                    @foreach ($roles as $roleName)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="two_factor_required_roles[]" value="{{ $roleName }}"
                                       id="tfa-{{ \Illuminate\Support\Str::slug($roleName) }}"
                                       @checked(in_array($roleName, old('two_factor_required_roles', $twoFactorRoles), true))>
                                <label class="form-check-label" for="tfa-{{ \Illuminate\Support\Str::slug($roleName) }}">{{ $roleName }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Verrechnungsreihenfolge für Zahlungseingänge <x-help-icon text="Eingehende Zahlungen werden in dieser Reihenfolge auf offene Posten verrechnet." /></div>
            <div class="card-body">
                @error('allocation_order')<div class="text-danger small">{{ $message }}</div>@enderror
                @error('allocation_order.*')<div class="text-danger small">{{ $message }}</div>@enderror
                <div class="row g-2">
                    @foreach (old('allocation_order', $allocationOrder) as $i => $bucket)
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="alloc-{{ $i }}">{{ $i + 1 }}. Position</label>
                            <select id="alloc-{{ $i }}" name="allocation_order[]" class="form-select form-select-sm">
                                @foreach ($bucketLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($bucket === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">Standard: Kosten, Gebühren, Verzugszinsen, Zinsen, Tilgung. Jede Position darf nur einmal vorkommen.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Dokumenten-Upload</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="max_size_kb">Maximale Dateigröße (KB) *</label>
                        <input type="number" id="max_size_kb" name="max_size_kb" min="128" max="1048576"
                               value="{{ old('max_size_kb', $maxSizeKb) }}"
                               class="form-control @error('max_size_kb') is-invalid @enderror" required>
                        @error('max_size_kb')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Aktuell: {{ number_format($maxSizeKb / 1024, 0, ',', '.') }} MB.</div>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn btn-primary mb-4">Einstellungen speichern</button>
    </form>
@endsection
