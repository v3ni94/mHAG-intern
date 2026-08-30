@extends('layouts.app')
@section('title', 'Aktienbewegung erfassen')
@section('content')
    <x-page-header title="Aktienbewegung erfassen" label="Aktien">
        <a href="{{ route('share-transactions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zum Register
        </a>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('share-transactions.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label required" for="type">Transaktionsart</label>
                    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">Bitte wählen ...</option>
                        @foreach ($types as $type)
                            @continue($type->value === 'correction')
                            <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Korrekturen entstehen ausschließlich als Gegenbuchung beim Storno.</div>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="seller_shareholder_id">Verkäufer / abgebender Aktionär</label>
                    <select name="seller_shareholder_id" id="seller_shareholder_id" class="form-select @error('seller_shareholder_id') is-invalid @enderror">
                        <option value="">Keiner (z. B. Kapitalerhöhung)</option>
                        @foreach ($shareholders as $shareholder)
                            <option value="{{ $shareholder->id }}" @selected(old('seller_shareholder_id') == $shareholder->id)>
                                {{ $shareholder->shareholder_number }} · {{ $shareholder->entity?->display_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('seller_shareholder_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="buyer_shareholder_id">Käufer / empfangender Aktionär</label>
                    <select name="buyer_shareholder_id" id="buyer_shareholder_id" class="form-select @error('buyer_shareholder_id') is-invalid @enderror">
                        <option value="">Keiner (z. B. Einziehung)</option>
                        @foreach ($shareholders as $shareholder)
                            <option value="{{ $shareholder->id }}" @selected(old('buyer_shareholder_id') == $shareholder->id)>
                                {{ $shareholder->shareholder_number }} · {{ $shareholder->entity?->display_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('buyer_shareholder_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label required" for="share_count">Anzahl Aktien</label>
                    <input type="number" name="share_count" id="share_count" min="1" step="1"
                           class="form-control @error('share_count') is-invalid @enderror" value="{{ old('share_count') }}" required>
                    @error('share_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="price_per_share">Kaufpreis je Aktie (EUR)</label>
                    <input type="text" name="price_per_share" id="price_per_share" inputmode="decimal"
                           class="form-control @error('price_per_share') is-invalid @enderror"
                           value="{{ old('price_per_share') }}" placeholder="z. B. 12,50">
                    @error('price_per_share')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="total_price">Gesamtkaufpreis (EUR)</label>
                    <input type="text" name="total_price" id="total_price" inputmode="decimal"
                           class="form-control @error('total_price') is-invalid @enderror"
                           value="{{ old('total_price') }}" placeholder="leer = automatisch">
                    <div class="form-text">Leer lassen: wird aus Anzahl mal Preis je Aktie berechnet.</div>
                    @error('total_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="contract_date">Vertragsdatum</label>
                    <input type="date" name="contract_date" id="contract_date" class="form-control @error('contract_date') is-invalid @enderror" value="{{ old('contract_date') }}">
                    @error('contract_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="economic_transfer_date">Wirtschaftlicher Übergang</label>
                    <input type="date" name="economic_transfer_date" id="economic_transfer_date"
                           class="form-control @error('economic_transfer_date') is-invalid @enderror"
                           value="{{ old('economic_transfer_date') }}" required>
                    <div class="form-text">Wirkungsdatum für die Bestandsberechnung.</div>
                    @error('economic_transfer_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="booking_date">Buchungsdatum</label>
                    <input type="date" name="booking_date" id="booking_date" class="form-control" value="{{ old('booking_date') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="resolution_id">Beschluss (optional)</label>
                    <select name="resolution_id" id="resolution_id" class="form-select @error('resolution_id') is-invalid @enderror">
                        <option value="">Kein Beschluss</option>
                        @foreach ($resolutions as $resolution)
                            <option value="{{ $resolution->id }}" @selected(old('resolution_id') == $resolution->id)>
                                {{ $resolution->resolution_number }} · {{ \Illuminate\Support\Str::limit($resolution->title, 40) }}
                            </option>
                        @endforeach
                    </select>
                    @error('resolution_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="contract_id">Vertrag (optional)</label>
                    <select name="contract_id" id="contract_id" class="form-select @error('contract_id') is-invalid @enderror">
                        <option value="">Kein Vertrag</option>
                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}" @selected(old('contract_id') == $contract->id)>
                                {{ $contract->contract_number }} · {{ \Illuminate\Support\Str::limit($contract->title, 40) }}
                            </option>
                        @endforeach
                    </select>
                    @error('contract_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="note">Notiz</label>
                    <textarea name="note" id="note" rows="3" class="form-control @error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Als Entwurf erfassen</button>
                    <span class="text-muted small ms-2">Die Bewegung verändert den Bestand erst nach der Wirksamsetzung.</span>
                </div>
            </form>
        </div>
    </div>
@endsection
