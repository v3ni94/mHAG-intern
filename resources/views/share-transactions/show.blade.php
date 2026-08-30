@extends('layouts.app')
@section('title', 'Aktienbewegung '.$transaction->transaction_number)
@section('content')
    <x-page-header :title="'Aktienbewegung '.$transaction->transaction_number" label="Aktien">
        <a href="{{ route('share-transactions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zum Register
        </a>
        @can('shares.finalize')
            @if (! in_array($transaction->status?->value, ['effective', 'cancelled'], true))
                <x-confirm-form :action="route('share-transactions.make-effective', $transaction)"
                                confirm="Diese Aktienbewegung wirksam setzen? Sie verändert danach den berechneten Bestand."
                                label="Wirksam setzen" icon="bi-check2-circle" class="btn btn-sm btn-primary" />
            @endif
            @if ($transaction->status?->value !== 'cancelled' && $reversals->isEmpty() && $transaction->type?->value !== 'correction')
                <x-confirm-form :action="route('share-transactions.cancel', $transaction)"
                                confirm="Diese Aktienbewegung stornieren? Wirksame Bewegungen werden per Gegenbuchung neutralisiert, nichts wird gelöscht."
                                label="Stornieren" icon="bi-x-circle" class="btn btn-sm btn-outline-danger" />
            @endif
        @endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Details</span>
                    <x-enum-badge :enum="$transaction->status" />
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Transaktionsart</dt>
                        <dd class="col-sm-8">{{ $transaction->type?->label() }}</dd>

                        <dt class="col-sm-4">Verkäufer / abgebend</dt>
                        <dd class="col-sm-8">
                            @if ($transaction->seller)
                                <a href="{{ route('shareholders.show', $transaction->seller) }}">
                                    {{ $transaction->seller->shareholder_number }} · {{ $transaction->seller->entity?->display_name }}
                                </a>
                            @else
                                <span class="text-muted">Gesellschaft (z. B. Kapitalmaßnahme)</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Käufer / empfangend</dt>
                        <dd class="col-sm-8">
                            @if ($transaction->buyer)
                                <a href="{{ route('shareholders.show', $transaction->buyer) }}">
                                    {{ $transaction->buyer->shareholder_number }} · {{ $transaction->buyer->entity?->display_name }}
                                </a>
                            @else
                                <span class="text-muted">Gesellschaft (z. B. Einziehung)</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Anzahl Aktien</dt>
                        <dd class="col-sm-8">{{ number_format($transaction->share_count, 0, ',', '.') }}</dd>

                        <dt class="col-sm-4">Kaufpreis je Aktie</dt>
                        <dd class="col-sm-8">
                            @if ($transaction->price_per_share !== null)
                                <x-money :amount="$transaction->price_per_share" />
                            @else
                                <span class="text-muted">nicht erfasst</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Gesamtkaufpreis</dt>
                        <dd class="col-sm-8">
                            @if ($transaction->total_price !== null)
                                <x-money :amount="$transaction->total_price" />
                            @else
                                <span class="text-muted">nicht erfasst</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Vertragsdatum</dt>
                        <dd class="col-sm-8">{{ format_date($transaction->contract_date) ?: 'nicht erfasst' }}</dd>

                        <dt class="col-sm-4">Wirtschaftlicher Übergang</dt>
                        <dd class="col-sm-8">{{ format_date($transaction->economic_transfer_date) }}</dd>

                        <dt class="col-sm-4">Buchungsdatum</dt>
                        <dd class="col-sm-8">{{ format_date($transaction->booking_date) ?: 'nicht erfasst' }}</dd>

                        @if ($transaction->note)
                            <dt class="col-sm-4">Notiz</dt>
                            <dd class="col-sm-8">{{ $transaction->note }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($transaction->status?->value === 'effective')
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Diese Bewegung ist wirksam und fließt in die Bestandsberechnung ein.
                </div>
            @elseif ($transaction->status?->value === 'cancelled')
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-1"></i>
                    Diese Bewegung wurde storniert und verändert den Bestand nicht.
                </div>
            @else
                <div class="alert alert-secondary">
                    <i class="bi bi-info-circle me-1"></i>
                    Nur wirksame Bewegungen verändern den berechneten Aktienbestand (Statusablauf: Entwurf, Prüfung, Vertrag, Unterschrift, Beschluss, wirksam).
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Verknüpfungen</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Beschluss</dt>
                        <dd class="col-7">
                            @if ($transaction->resolution)
                                <a href="{{ route('resolutions.show', $transaction->resolution) }}">{{ $transaction->resolution->resolution_number }}</a>
                            @else
                                <span class="text-muted">keiner</span>
                            @endif
                        </dd>
                        <dt class="col-5">Vertrag</dt>
                        <dd class="col-7">
                            @if ($transaction->contract)
                                {{ $transaction->contract->contract_number }}
                            @else
                                <span class="text-muted">keiner</span>
                            @endif
                        </dd>
                        <dt class="col-5">Erfasst am</dt>
                        <dd class="col-7">{{ format_datetime($transaction->created_at) }}</dd>
                    </dl>
                </div>
            </div>

            @if ($transaction->reversalOf)
                <div class="alert alert-warning">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Gegenbuchung zu
                    <a href="{{ route('share-transactions.show', $transaction->reversalOf) }}">{{ $transaction->reversalOf->transaction_number }}</a>.
                </div>
            @endif

            @if ($reversals->isNotEmpty())
                <div class="alert alert-warning">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Diese Bewegung wurde per Gegenbuchung storniert:
                    @foreach ($reversals as $reversal)
                        <a href="{{ route('share-transactions.show', $reversal) }}">{{ $reversal->transaction_number }}</a>
                    @endforeach
                </div>
            @endif

            <div class="card">
                <div class="card-header">Dokumente</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($transaction->documentLinks as $link)
                            @if ($link->document)
                                <li class="list-group-item small">
                                    <i class="bi bi-file-earmark me-1"></i>{{ $link->document->original_filename }}
                                </li>
                            @endif
                        @empty
                            <li class="list-group-item text-muted small">Keine Dokumente verknüpft.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
