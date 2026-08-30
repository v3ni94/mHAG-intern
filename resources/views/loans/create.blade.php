@extends('layouts.app')

@section('title', 'Neues Darlehen')

@section('content')
    <x-page-header title="Neues Darlehen" label="Finanzen · Darlehen">
        <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <form method="POST" action="{{ route('loans.store') }}">
        @csrf

        @include('loans._form', ['mode' => 'create'])

        {{--
            Auszahlungen (Abschnitt 31): In der Praxis wird ein Darlehen in
            mehreren Teilbetraegen an verschiedenen Tagen ausgezahlt. Da die
            Zinsrechnung dem Kapitalverlauf taggenau folgt, werden die Zeilen
            bereits beim Anlegen erfasst. "Bestaetigt ausgezahlt" erzeugt
            sofort die Kapitalbuchung mit Wirkungsdatum = Auszahlungsdatum,
            "Geplant" nur den SOLL-Eintrag.
        --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    Auszahlungen
                    <x-help-icon text="Beliebig viele Teilauszahlungen mit Datum und Betrag. Die Summe darf den Darlehensrahmen nicht übersteigen; eine kleinere Summe ist als Teilauszahlung zulässig. Ohne Zeile wird keine Auszahlung erfasst." />
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="disb-add-row">
                    <i class="bi bi-plus-lg"></i> Zeile hinzufügen
                </button>
            </div>
            <div class="card-body">
                @error('disbursements')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="disb-rows">
                        <thead>
                            <tr>
                                <th style="min-width: 160px;">Datum *</th>
                                <th style="min-width: 150px;">Betrag (EUR) *</th>
                                <th style="min-width: 200px;">Status *</th>
                                <th style="min-width: 200px;">Herkunft (bei bestätigt)</th>
                                <th style="min-width: 140px;">Referenz</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php($rows = old('disbursements', [['date' => '', 'amount' => '', 'status' => 'confirmed', 'origin' => 'manual_entered', 'reference' => '']]))
                            @foreach ($rows as $index => $row)
                                <tr class="disb-row">
                                    <td>
                                        <input type="date" name="disbursements[{{ $index }}][date]"
                                               class="form-control form-control-sm disb-date @error('disbursements.'.$index.'.date') is-invalid @enderror"
                                               value="{{ $row['date'] ?? '' }}">
                                        @error('disbursements.'.$index.'.date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" name="disbursements[{{ $index }}][amount]"
                                               class="form-control form-control-sm disb-amount @error('disbursements.'.$index.'.amount') is-invalid @enderror"
                                               value="{{ $row['amount'] ?? '' }}" placeholder="z. B. 50.000,00">
                                        @error('disbursements.'.$index.'.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <select name="disbursements[{{ $index }}][status]" class="form-select form-select-sm">
                                            <option value="confirmed" @selected(($row['status'] ?? 'confirmed') === 'confirmed')>Bestätigt ausgezahlt</option>
                                            <option value="planned" @selected(($row['status'] ?? '') === 'planned')>Geplant</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="disbursements[{{ $index }}][origin]" class="form-select form-select-sm">
                                            <option value="manual_entered" @selected(($row['origin'] ?? 'manual_entered') === 'manual_entered')>Manuell erfasst</option>
                                            <option value="manual_confirmed" @selected(($row['origin'] ?? '') === 'manual_confirmed')>Manuell bestätigt</option>
                                            <option value="bank_import" @selected(($row['origin'] ?? '') === 'bank_import')>Bankseitig bestätigt</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="disbursements[{{ $index }}][reference]" class="form-control form-control-sm"
                                               value="{{ $row['reference'] ?? '' }}">
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger disb-remove" title="Zeile entfernen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="form-text mb-0">
                    Bereits erfolgte Auszahlungen bestehender Verträge mit "Bestätigt ausgezahlt" und dem tatsächlichen Datum erfassen.
                    Die erste Zeile wird mit Wirkungsbeginn und Darlehenssumme vorbelegt.
                </p>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Darlehen anlegen</button>
            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
        <p class="text-muted small">
            Die Darlehensnummer wird automatisch vergeben. Das Darlehen startet im Status "Entwurf".
            Bei einem Wirkungsbeginn in der Vergangenheit berechnet das System alle Werte ab diesem Datum automatisch.
        </p>
    </form>
@endsection

@push('scripts')
    {{-- Wiederholbare Auszahlungszeilen; reines JavaScript, kein Framework. --}}
    <script>
        (function () {
            const table = document.getElementById('disb-rows');
            if (!table) { return; }
            const body = table.querySelector('tbody');
            const addButton = document.getElementById('disb-add-row');

            function reindex() {
                body.querySelectorAll('tr.disb-row').forEach(function (row, index) {
                    row.querySelectorAll('input, select').forEach(function (field) {
                        if (field.name) {
                            field.name = field.name.replace(/disbursements\[\d*\]/, 'disbursements[' + index + ']');
                        }
                    });
                });
            }

            function bindRemove(row) {
                const button = row.querySelector('.disb-remove');
                if (!button) { return; }
                button.addEventListener('click', function () {
                    if (body.querySelectorAll('tr.disb-row').length <= 1) {
                        row.querySelectorAll('input').forEach(function (field) { field.value = ''; });
                        return;
                    }
                    row.remove();
                    reindex();
                });
            }

            body.querySelectorAll('tr.disb-row').forEach(bindRemove);

            if (addButton) {
                addButton.addEventListener('click', function () {
                    const first = body.querySelector('tr.disb-row');
                    const clone = first.cloneNode(true);
                    clone.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
                    clone.querySelectorAll('input').forEach(function (field) {
                        field.value = '';
                        field.classList.remove('is-invalid');
                    });
                    body.appendChild(clone);
                    bindRemove(clone);
                    reindex();
                });
            }

            // Vorbelegung der ersten Zeile: Wirkungsbeginn und Darlehenssumme.
            const effectiveFrom = document.getElementById('effective_from');
            const principal = document.getElementById('principal_amount');
            const firstDate = body.querySelector('.disb-date');
            const firstAmount = body.querySelector('.disb-amount');

            function prefill() {
                if (firstDate && effectiveFrom && !firstDate.value) { firstDate.value = effectiveFrom.value; }
                if (firstAmount && principal && !firstAmount.value) { firstAmount.value = principal.value; }
            }

            if (effectiveFrom) { effectiveFrom.addEventListener('change', prefill); }
            if (principal) { principal.addEventListener('change', prefill); }
            prefill();
        })();
    </script>
@endpush
