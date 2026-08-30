@extends('layouts.app')

@section('title', 'Darlehen '.$loan->loan_number)

@section('content')
    {{-- Kopf (Abschnitt 135): Nummer, Parteien, Status --}}
    <x-page-header :title="$loan->loan_number.($loan->title ? ' · '.$loan->title : '')" label="Finanzen · Darlehen">
        <x-enum-badge :enum="$loan->status" />
        @if ($canUpdate)
            <a href="{{ route('loans.edit', $loan) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
            @if (count($transitions))
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <i class="bi bi-arrow-repeat"></i> Statuswechsel
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 300px;">
                        <form method="POST" action="{{ route('loans.transition', $loan) }}">
                            @csrf
                            <label class="form-label small" for="transition-status">Neuer Status</label>
                            <select id="transition-status" name="status" class="form-select form-select-sm mb-2" required>
                                @foreach ($transitions as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            <label class="form-label small" for="transition-date">Wirkungsdatum (optional)</label>
                            <input type="date" id="transition-date" name="effective_date" class="form-control form-control-sm mb-2">
                            <label class="form-label small" for="transition-note">Notiz (optional)</label>
                            <input type="text" id="transition-note" name="note" class="form-control form-control-sm mb-2" maxlength="2000">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Status ändern</button>
                        </form>
                    </div>
                </div>
            @endif
        @endif
        {{-- Forderungsaufstellung (Abschnitt 51): Stichtag wählen, PDF erzeugen --}}
        <form method="GET" action="{{ route('loans.statement', $loan) }}" target="_blank" class="d-flex gap-1 align-items-center">
            <label class="visually-hidden" for="statement-date">Stichtag</label>
            <input type="date" id="statement-date" name="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" style="width: 160px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">
                <i class="bi bi-file-earmark-pdf"></i> Forderungsaufstellung
            </button>
        </form>
        @if ($canArchive && $loan->status !== \App\Enums\LoanStatus::Archived)
            <x-confirm-form :action="route('loans.archive', $loan)" method="POST"
                            confirm="Darlehen wirklich archivieren?" label="Archivieren" icon="bi-archive"
                            class="btn btn-sm btn-outline-danger" />
        @endif
    </x-page-header>

    <div class="row g-2 mb-2 small text-muted">
        <div class="col-auto">
            <span class="versal-label">Darlehensgeber</span>
            {{ $loan->lender?->display_name }}
        </div>
        <div class="col-auto">
            <span class="versal-label">Darlehensnehmer</span>
            {{ $loan->borrower?->display_name }}
        </div>
        @if ($loan->loanType)
            <div class="col-auto">
                <span class="versal-label">Art</span>
                {{ $loan->loanType->name }}
            </div>
        @endif
    </div>

    {{-- KPI-Zeile (Abschnitt 135) --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Ursprungsbetrag" :value="format_money($loan->principal_amount)" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Ausgezahlt" :value="format_money($balances['disbursed'] ?? '0.00')" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Offenes Kapital" :value="format_money($balances['principal_outstanding'] ?? '0.00')" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Zinssatz" :value="$currentRate !== null ? format_percent($currentRate) : 'ohne'" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Offene Zinsen" :value="format_money($balances['interest_open'] ?? '0.00')"
                        :severity="\App\Support\Money::isPositive($balances['interest_open'] ?? '0.00') ? 'warning' : null" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Nächste Fälligkeit"
                        :value="! empty($balances['next_due_date']) ? format_date($balances['next_due_date']) : 'keine'"
                        :hint="! empty($balances['next_due_amount']) && \App\Support\Money::isPositive($balances['next_due_amount']) ? format_money($balances['next_due_amount']) : null" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Kontostand" :value="format_money($balances['account_balance'] ?? '0.00')"
                        help="Summe aller Buchungen des Darlehenskontos bis heute: Auszahlungen, Tilgungen, Zahlungen, Zinszuschreibungen, Verzugszinsen und Stornos. Enthält nur, was tatsächlich gebucht ist." />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card label="Gesamtforderung" :value="format_money($balances['total_receivable'] ?? '0.00')"
                        help="Kontostand zuzüglich der bis heute entstandenen, aber noch nicht gebuchten Soll-Positionen aus dem Zahlungsplan, also offene Zinsen und Gebühren. Deshalb regelmäßig höher als der Kontostand." />
        </div>
    </div>

    {{-- Reiter (Abschnitt 135) --}}
    @php
        $tabs = [
            'uebersicht' => 'Übersicht',
            'konto' => 'Konto',
            'zahlungsplan' => 'Zahlungsplan',
            'soll-ist' => 'Soll/Ist',
            'zahlungen' => 'Zahlungen',
            'zinsen' => 'Zinsen',
            'gebuehren' => 'Gebühren',
            'auszahlungen' => 'Auszahlungen',
            'vertraege' => 'Verträge',
            'sicherheiten' => 'Sicherheiten',
            'dokumente' => 'Dokumente',
            'chronik' => 'Chronik',
            'neuberechnungen' => 'Neuberechnungen',
        ];
        $activeTab = array_key_exists($tab, $tabs) ? $tab : 'uebersicht';
    @endphp
    <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" style="white-space: nowrap;">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                   href="{{ route('loans.show', [$loan, 'tab' => $key]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    @include('loans.tabs.'.$activeTab)
@endsection
