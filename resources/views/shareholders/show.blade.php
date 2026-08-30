@extends('layouts.app')
@section('title', 'Aktionär '.$shareholder->shareholder_number)
@section('content')
    <x-page-header :title="$shareholder->entity?->display_name ?? $shareholder->shareholder_number" label="Aktionärsakte">
        <a href="{{ route('shareholders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <x-kpi-card label="Aktienbestand" :value="number_format($shares, 0, ',', '.')"
                        :hint="'Stichtag '.format_date($asOf)" icon="bi-collection" />
        </div>
        <div class="col-md-3">
            <x-kpi-card label="Anteil" :value="format_percent($percentage)" icon="bi-percent" />
        </div>
        <div class="col-md-3">
            <x-kpi-card label="Aktionärsnummer" :value="$shareholder->shareholder_number" icon="bi-hash" />
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body py-2">
                    <form method="GET" action="{{ route('shareholders.show', $shareholder) }}">
                        <label class="form-label small text-muted mb-1" for="as_of">Bestand zum Stichtag</label>
                        <div class="input-group input-group-sm">
                            <input type="date" name="as_of" id="as_of" class="form-control" value="{{ $asOf->format('Y-m-d') }}">
                            <button class="btn btn-outline-secondary">Anzeigen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Stammdaten</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Name</dt>
                        <dd class="col-7">{{ $shareholder->entity?->display_name }}</dd>
                        <dt class="col-5">Typ</dt>
                        <dd class="col-7">{{ $shareholder->entity?->type?->label() ?? '' }}</dd>
                        @if ($shareholder->entity?->primaryAddress())
                            <dt class="col-5">Anschrift</dt>
                            <dd class="col-7">
                                {{ $shareholder->entity->primaryAddress()->street }} {{ $shareholder->entity->primaryAddress()->house_number }}<br>
                                {{ $shareholder->entity->primaryAddress()->postal_code }} {{ $shareholder->entity->primaryAddress()->city }}
                            </dd>
                        @endif
                        @if ($shareholder->entity?->primaryEmail())
                            <dt class="col-5">E-Mail</dt>
                            <dd class="col-7">{{ $shareholder->entity->primaryEmail() }}</dd>
                        @endif
                        <dt class="col-5">Eintritt</dt>
                        <dd class="col-7">{{ format_date($shareholder->joined_on) ?: 'nicht erfasst' }}</dd>
                        <dt class="col-5">Austritt</dt>
                        <dd class="col-7">{{ format_date($shareholder->left_on) ?: '' }}</dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">
                            @if ($shareholder->status === 'active')
                                <x-status-badge severity="success" label="Aktiv" />
                            @else
                                <x-status-badge severity="neutral" label="Inaktiv" />
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @can('shares.prepare')
                <div class="card mb-3">
                    <div class="card-header">Daten pflegen</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('shareholders.update', $shareholder) }}">
                            @csrf @method('PUT')
                            <div class="mb-2">
                                <label class="form-label small" for="joined_on_edit">Eintritt</label>
                                <input type="date" name="joined_on" id="joined_on_edit" class="form-control form-control-sm"
                                       value="{{ old('joined_on', $shareholder->joined_on?->format('Y-m-d')) }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="left_on">Austritt</label>
                                <input type="date" name="left_on" id="left_on" class="form-control form-control-sm"
                                       value="{{ old('left_on', $shareholder->left_on?->format('Y-m-d')) }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="status">Status</label>
                                <select name="status" id="status" class="form-select form-select-sm">
                                    <option value="active" @selected(old('status', $shareholder->status) === 'active')>Aktiv</option>
                                    <option value="inactive" @selected(old('status', $shareholder->status) === 'inactive')>Inaktiv</option>
                                </select>
                            </div>
                            @if (auth()->user()?->isInternal())
                                <div class="mb-2">
                                    <label class="form-label small" for="notes">Interne Notizen</label>
                                    <textarea name="notes" id="notes" rows="3" class="form-control form-control-sm">{{ old('notes', $shareholder->notes) }}</textarea>
                                </div>
                            @endif
                            <button class="btn btn-primary btn-sm">Speichern</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Dokumente</span>
                    @can('documents.upload')
                        <a href="{{ route('documents.create', ['link_type' => 'entity', 'link_id' => $shareholder->entity_id]) }}"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-upload"></i> Hochladen
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($shareholder->documentLinks->merge($entityDocuments) as $link)
                            @if ($link->document)
                                <li class="list-group-item small d-flex justify-content-between">
                                    <span><i class="bi bi-file-earmark me-1"></i>{{ $link->document->original_filename }}</span>
                                    <span class="text-muted">{{ format_date($link->document->created_at) }}</span>
                                </li>
                            @endif
                        @empty
                            <li class="list-group-item text-muted small">Keine Dokumente verknüpft.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Transaktionshistorie</span>
                    @can('shares.prepare')
                        <a href="{{ route('share-transactions.create') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-plus-lg"></i> Neue Bewegung
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Art</th>
                                <th>Wirtschaftlicher Übergang</th>
                                <th class="text-end">Stück</th>
                                <th class="text-end">Wirkung</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($transactions as $t)
                                @php
                                    $isBuyer = $t->buyer_shareholder_id === $shareholder->id;
                                    $isCapitalOut = in_array($t->type?->value, ['redemption', 'capital_decrease'], true);
                                    $effect = $isBuyer && ! $isCapitalOut ? $t->share_count : -$t->share_count;
                                @endphp
                                <tr>
                                    <td><a href="{{ route('share-transactions.show', $t) }}">{{ $t->transaction_number }}</a></td>
                                    <td>{{ $t->type?->label() }}</td>
                                    <td>{{ format_date($t->economic_transfer_date) }}</td>
                                    <td class="text-end">{{ number_format($t->share_count, 0, ',', '.') }}</td>
                                    <td class="text-end {{ $effect >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $effect >= 0 ? '+' : '' }}{{ number_format($effect, 0, ',', '.') }}
                                    </td>
                                    <td><x-enum-badge :enum="$t->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">Keine Transaktionen vorhanden.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($transactions->hasPages())
                    <div class="card-footer">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
