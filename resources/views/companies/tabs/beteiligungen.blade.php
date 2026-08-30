{{-- Unternehmensakte: Unternehmensbeziehungen (Abschnitt 8) und Beteiligungen der Holding --}}
@php
    $canEdit = auth()->user()->can('companies.update');
    $relationships = $entity->relationshipsAsA->map(fn ($r) => ['rel' => $r, 'other' => $r->entityB, 'direction' => 'a'])
        ->concat($entity->relationshipsAsB->map(fn ($r) => ['rel' => $r, 'other' => $r->entityA, 'direction' => 'b']));
    $hasInvestmentRoute = \Illuminate\Support\Facades\Route::has('investments.index');
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Unternehmensbeziehungen</span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#relationship-create">
                <i class="bi bi-plus-lg"></i> Beziehung hinzufügen
            </button>
        @endif
    </div>

    @if ($canEdit)
        <div class="collapse {{ $errors->any() && old('_form') === 'relationship-create' ? 'show' : '' }}" id="relationship-create">
            <div class="card-body hairline-top">
                <form method="POST" action="{{ route('companies.relationships.store', $entity) }}">
                    @csrf
                    <input type="hidden" name="_form" value="relationship-create">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Verbundenes Unternehmen *</label>
                            <select name="entity_b_id" class="form-select form-select-sm @error('entity_b_id') is-invalid @enderror" required>
                                <option value="">Bitte wählen</option>
                                @foreach (($companyOptions ?? collect()) as $option)
                                    <option value="{{ $option->id }}" @selected((string) old('entity_b_id') === (string) $option->id)>{{ $option->display_name }}</option>
                                @endforeach
                            </select>
                            @error('entity_b_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Beziehungsart *
                                <x-help-icon text="Aus Sicht dieser Akte: das gewählte Unternehmen ist z. B. die Tochtergesellschaft" />
                            </label>
                            <select name="relationship_type" class="form-select form-select-sm @error('relationship_type') is-invalid @enderror" required>
                                @foreach (\App\Enums\RelationshipType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('relationship_type') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('relationship_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Beteiligungsquote (%)</label>
                            <input type="text" name="share_percentage" value="{{ old('share_percentage') }}"
                                   class="form-control form-control-sm @error('share_percentage') is-invalid @enderror" placeholder="z. B. 25,5">
                            @error('share_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Anzahl Anteile</label>
                            <input type="number" name="share_count" value="{{ old('share_count') }}" min="0"
                                   class="form-control form-control-sm @error('share_count') is-invalid @enderror">
                            @error('share_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Beginn</label>
                            <input type="date" name="valid_from" value="{{ old('valid_from') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ende</label>
                            <input type="date" name="valid_until" value="{{ old('valid_until') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bemerkung</label>
                            <input type="text" name="note" value="{{ old('note') }}" class="form-control form-control-sm">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check-lg"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    @if ($relationships->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-diagram-3" message="Keine Unternehmensbeziehungen hinterlegt." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Unternehmen</th>
                    <th>Beziehungsart</th>
                    <th class="num">Quote</th>
                    <th class="num">Anteile</th>
                    <th>Zeitraum</th>
                    <th>Bemerkung</th>
                    @if ($canEdit)<th class="text-end">Aktionen</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($relationships as $row)
                    @php($rel = $row['rel'])
                    <tr>
                        <td>
                            @if ($row['other'])
                                <a href="{{ route('companies.show', $row['other']) }}" class="text-decoration-none">{{ $row['other']->display_name }}</a>
                            @endif
                            @if ($row['direction'] === 'b')
                                <div class="text-muted small">Beziehung wurde in der Akte des Partners erfasst</div>
                            @endif
                        </td>
                        <td><x-enum-badge :enum="$rel->relationship_type" /></td>
                        <td class="num">{{ $rel->share_percentage !== null ? format_percent($rel->share_percentage) : '' }}</td>
                        <td class="num">{{ $rel->share_count !== null ? number_format((int) $rel->share_count, 0, ',', '.') : '' }}</td>
                        <td>
                            @if ($rel->valid_from || $rel->valid_until)
                                {{ format_date($rel->valid_from) }} bis {{ format_date($rel->valid_until) ?: 'offen' }}
                            @endif
                        </td>
                        <td class="small">{{ $rel->note }}</td>
                        @if ($canEdit)
                            <td class="text-end">
                                @if ($row['direction'] === 'a')
                                    <x-confirm-form :action="route('companies.relationships.destroy', [$entity, $rel])" method="DELETE"
                                                    confirm="Diese Unternehmensbeziehung wirklich löschen?" icon="bi-trash" label="" />
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-header">Beteiligungen der Müller Holding AG an diesem Unternehmen</div>
    @if (($investments ?? collect())->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-pie-chart" message="Keine Beteiligung erfasst." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th class="num">Quote</th>
                    <th class="num">Anteile</th>
                    <th>Erworben am</th>
                    <th class="num">Anschaffungskosten</th>
                    <th>Status</th>
                    @if ($hasInvestmentRoute)<th class="text-end">Aktionen</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($investments as $investment)
                    <tr>
                        <td class="num">{{ format_percent($investment->share_percentage) }}</td>
                        <td class="num">{{ $investment->share_count !== null ? number_format((int) $investment->share_count, 0, ',', '.') : '' }}</td>
                        <td>{{ format_date($investment->acquired_on) }}</td>
                        <td class="num"><x-money :amount="$investment->acquisition_cost" /></td>
                        <td>
                            @if ($investment->status === 'active')
                                <x-status-badge severity="success" icon="bi-check-circle-fill" label="Aktiv" />
                            @else
                                <x-status-badge severity="neutral" icon="bi-dash-circle" :label="$investment->status === 'sold' ? 'Verkauft' : 'Liquidiert'" />
                            @endif
                        </td>
                        @if ($hasInvestmentRoute)
                            <td class="text-end">
                                <a href="{{ route('investments.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i> Beteiligungsmodul
                                </a>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
