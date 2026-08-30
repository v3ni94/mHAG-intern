{{-- Sicherheiten und Bürgschaften (Abschnitte 66/67) --}}
@php($warningDate = now()->addDays(\App\Http\Controllers\SecurityController::EXPIRY_WARNING_DAYS)->toDateString())

<div class="card mb-3">
    <div class="card-header">Sicherheiten</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Art</th>
                    <th>Sicherungsgeber</th>
                    <th class="text-end">Nominalwert</th>
                    <th class="text-end">Interner Wert</th>
                    <th>Rang</th>
                    <th>Beginn</th>
                    <th>Ende</th>
                    <th>Status</th>
                    @if ($canUpdate)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->securities as $security)
                    <tr>
                        <td>{{ $security->type?->label() }}</td>
                        <td>{{ $security->provider?->display_name }}</td>
                        <td class="text-end">@if ($security->nominal_value !== null)<x-money :amount="$security->nominal_value" />@endif</td>
                        <td class="text-end">@if ($security->internal_value !== null)<x-money :amount="$security->internal_value" />@endif</td>
                        <td>{{ $security->rank }}</td>
                        <td>{{ $security->valid_from ? format_date($security->valid_from) : '' }}</td>
                        <td>
                            {{ $security->valid_until ? format_date($security->valid_until) : 'unbefristet' }}
                            @if ($security->status === 'active' && $security->valid_until)
                                @if ($security->valid_until->toDateString() < now()->toDateString())
                                    <x-status-badge severity="danger" label="Abgelaufen" />
                                @elseif ($security->valid_until->toDateString() <= $warningDate)
                                    <x-status-badge severity="warning" label="Läuft bald ab" />
                                @endif
                            @endif
                        </td>
                        <td>
                            @switch($security->status)
                                @case('released') <x-status-badge severity="neutral" label="Freigegeben" /> @break
                                @case('expired') <x-status-badge severity="danger" label="Abgelaufen" /> @break
                                @default <x-status-badge severity="success" label="Aktiv" />
                            @endswitch
                        </td>
                        @if ($canUpdate)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#security-edit-{{ $security->id }}" title="Bearbeiten"><i class="bi bi-pencil"></i></button>
                                <x-confirm-form :action="route('loans.securities.destroy', [$loan, $security])" method="DELETE"
                                                confirm="Sicherheit wirklich entfernen?" label="" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                            </td>
                        @endif
                    </tr>
                    @if ($canUpdate)
                        <tr class="collapse" id="security-edit-{{ $security->id }}">
                            <td colspan="9" class="bg-light">
                                <form method="POST" action="{{ route('loans.securities.update', [$loan, $security]) }}" class="row g-2 align-items-end p-2">
                                    @csrf
                                    @method('PUT')
                                    @include('loans.tabs._security-fields', ['security' => $security, 'suffix' => '-'.$security->id])
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $canUpdate ? 9 : 8 }}"><x-empty-state icon="bi-shield-check" message="Keine Sicherheiten erfasst." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canUpdate)
        <div class="card-footer">
            <form method="POST" action="{{ route('loans.securities.store', $loan) }}" class="row g-2 align-items-end">
                @csrf
                @include('loans.tabs._security-fields', ['security' => null, 'suffix' => '-neu'])
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Sicherheit anlegen</button>
                </div>
            </form>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-header">Bürgschaften</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Bürge</th>
                    <th>Bürgschaftsart</th>
                    <th class="text-end">Höchstbetrag</th>
                    <th>Beginn</th>
                    <th>Ende</th>
                    <th>Status</th>
                    @if ($canUpdate)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->guarantees as $guarantee)
                    <tr>
                        <td>{{ $guarantee->guarantor?->display_name }}</td>
                        <td>{{ $guarantee->guarantee_type }}</td>
                        <td class="text-end">@if ($guarantee->max_amount !== null)<x-money :amount="$guarantee->max_amount" />@endif</td>
                        <td>{{ $guarantee->valid_from ? format_date($guarantee->valid_from) : '' }}</td>
                        <td>
                            {{ $guarantee->valid_until ? format_date($guarantee->valid_until) : 'unbefristet' }}
                            @if ($guarantee->status === 'active' && $guarantee->valid_until && $guarantee->valid_until->toDateString() <= $warningDate)
                                <x-status-badge severity="warning" label="Läuft bald ab" />
                            @endif
                        </td>
                        <td>
                            @switch($guarantee->status)
                                @case('released') <x-status-badge severity="neutral" label="Freigegeben" /> @break
                                @case('expired') <x-status-badge severity="danger" label="Abgelaufen" /> @break
                                @default <x-status-badge severity="success" label="Aktiv" />
                            @endswitch
                        </td>
                        @if ($canUpdate)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#guarantee-edit-{{ $guarantee->id }}" title="Bearbeiten"><i class="bi bi-pencil"></i></button>
                                <x-confirm-form :action="route('loans.guarantees.destroy', [$loan, $guarantee])" method="DELETE"
                                                confirm="Bürgschaft wirklich entfernen?" label="" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
                            </td>
                        @endif
                    </tr>
                    @if ($canUpdate)
                        <tr class="collapse" id="guarantee-edit-{{ $guarantee->id }}">
                            <td colspan="7" class="bg-light">
                                <form method="POST" action="{{ route('loans.guarantees.update', [$loan, $guarantee]) }}" class="row g-2 align-items-end p-2">
                                    @csrf
                                    @method('PUT')
                                    @include('loans.tabs._guarantee-fields', ['guarantee' => $guarantee, 'suffix' => '-'.$guarantee->id])
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $canUpdate ? 7 : 6 }}"><x-empty-state icon="bi-person-check" message="Keine Bürgschaften erfasst." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canUpdate)
        <div class="card-footer">
            <form method="POST" action="{{ route('loans.guarantees.store', $loan) }}" class="row g-2 align-items-end">
                @csrf
                @include('loans.tabs._guarantee-fields', ['guarantee' => null, 'suffix' => '-neu'])
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Bürgschaft anlegen</button>
                </div>
            </form>
        </div>
    @endif
</div>
