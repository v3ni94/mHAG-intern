<?php

namespace App\Http\Controllers;

use App\Http\Requests\Holding\StoreShareholderRequest;
use App\Http\Requests\Holding\UpdateShareholderRequest;
use App\Models\Entity;
use App\Models\Shareholder;
use App\Models\ShareholderListSnapshot;
use App\Models\ShareTransaction;
use App\Services\AuditService;
use App\Services\Holding\ShareholdingService;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Aktionärsstamm (Abschnitt 77) mit berechnetem Bestand und
 * stichtagsfähiger Struktur (Abschnitt 81).
 */
class ShareholderController extends Controller
{
    public function __construct(private readonly ShareholdingService $shareholding)
    {
    }

    public function index(Request $request)
    {
        $request->validate(
            ['as_of' => ['nullable', 'date']],
            ['as_of.date' => 'Der Stichtag muss ein gültiges Datum sein.'],
        );

        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : Carbon::now();

        $holdings = $this->shareholding->holdingsAsOf($asOf);
        $holdingsById = $holdings->keyBy(fn (array $row) => $row['shareholder']->id);

        // Alle Aktionäre anzeigen, auch mit Bestand 0 (z. B. nach Vollverkauf).
        $rows = Shareholder::query()
            ->with('entity')
            ->orderBy('shareholder_number')
            ->get()
            ->map(function (Shareholder $shareholder) use ($holdingsById) {
                $holding = $holdingsById->get($shareholder->id);

                return [
                    'shareholder' => $shareholder,
                    'shares' => $holding['shares'] ?? 0,
                    'percentage' => $holding['percentage'] ?? '0.000000',
                ];
            })
            ->sortByDesc('shares')
            ->values();

        $snapshots = ShareholderListSnapshot::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Entities ohne Aktionärsdatensatz für das Anlegen-Formular
        $availableEntities = Entity::query()
            ->whereDoesntHave('shareholder')
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return view('shareholders.index', [
            'rows' => $rows,
            'asOf' => $asOf,
            'isHistorical' => ! $asOf->isSameDay(Carbon::now()),
            'totalShares' => $this->shareholding->totalShares(),
            'outstanding' => $rows->sum('shares'),
            'snapshots' => $snapshots,
            'availableEntities' => $availableEntities,
        ]);
    }

    public function store(StoreShareholderRequest $request)
    {
        $data = $request->validated();
        $data['shareholder_number'] = $data['shareholder_number'] ?? NumberSequenceService::next('AKT', 4);
        $data['status'] = 'active';

        $shareholder = Shareholder::create($data);

        AuditService::log('shareholders.created', $shareholder, [], $data);

        return redirect()
            ->route('shareholders.show', $shareholder)
            ->with('success', sprintf('Aktionär %s wurde angelegt.', $shareholder->shareholder_number));
    }

    public function show(Request $request, Shareholder $shareholder)
    {
        $request->validate(
            ['as_of' => ['nullable', 'date']],
            ['as_of.date' => 'Der Stichtag muss ein gültiges Datum sein.'],
        );

        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : Carbon::now();

        $shareholder->load([
            'entity.person',
            'entity.company',
            'entity.addresses',
            'entity.contactDetails',
            'documentLinks.document',
        ]);

        $shares = $this->shareholding->sharesOf($shareholder, $asOf);

        $transactions = ShareTransaction::query()
            ->with(['buyer.entity', 'seller.entity'])
            ->where(function ($q) use ($shareholder) {
                $q->where('buyer_shareholder_id', $shareholder->id)
                    ->orWhere('seller_shareholder_id', $shareholder->id);
            })
            ->orderByDesc('economic_transfer_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $entityDocuments = $shareholder->entity
            ? $shareholder->entity->documentLinks()->with('document')->get()
            : collect();

        return view('shareholders.show', [
            'shareholder' => $shareholder,
            'asOf' => $asOf,
            'shares' => $shares,
            'percentage' => $this->shareholding->percentage($shares),
            'transactions' => $transactions,
            'entityDocuments' => $entityDocuments,
        ]);
    }

    public function update(UpdateShareholderRequest $request, Shareholder $shareholder)
    {
        $old = $shareholder->only(['joined_on', 'left_on', 'status', 'notes']);
        $shareholder->update($request->validated());

        AuditService::log('shareholders.updated', $shareholder, $old, $request->validated());

        return redirect()
            ->route('shareholders.show', $shareholder)
            ->with('success', 'Aktionärsdaten wurden aktualisiert.');
    }
}
