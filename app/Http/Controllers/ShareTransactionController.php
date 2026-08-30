<?php

namespace App\Http\Controllers;

use App\Enums\ShareTransactionStatus;
use App\Enums\ShareTransactionType;
use App\Http\Requests\Holding\StoreShareTransactionRequest;
use App\Models\Contract;
use App\Models\Resolution;
use App\Models\Shareholder;
use App\Models\ShareTransaction;
use App\Services\AuditService;
use App\Services\Holding\ShareholdingService;
use App\Services\NumberSequenceService;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * Aktienbewegungen (Abschnitte 78 bis 80): Register, Erfassung,
 * Wirksamsetzung und Storno per Gegenbuchung.
 */
class ShareTransactionController extends Controller
{
    public function __construct(private readonly ShareholdingService $shareholding)
    {
    }

    public function index(Request $request)
    {
        $query = ShareTransaction::query()->with(['buyer.entity', 'seller.entity']);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('year')) {
            $query->whereYear('economic_transfer_date', (int) $request->input('year'));
        }

        $transactions = $query
            ->orderByDesc('economic_transfer_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Jahresliste portabel für SQLite (Tests) und MariaDB (Produktion)
        $driver = ShareTransaction::query()->getConnection()->getDriverName();
        $yearExpression = $driver === 'sqlite'
            ? "strftime('%Y', economic_transfer_date)"
            : 'YEAR(economic_transfer_date)';
        $years = ShareTransaction::query()
            ->selectRaw($yearExpression.' as y')
            ->distinct()
            ->pluck('y')
            ->filter()
            ->sortDesc()
            ->values();

        return view('share-transactions.index', [
            'transactions' => $transactions,
            'types' => ShareTransactionType::cases(),
            'statuses' => ShareTransactionStatus::cases(),
            'years' => $years,
            'filters' => $request->only(['type', 'status', 'year']),
        ]);
    }

    public function create()
    {
        return view('share-transactions.create', [
            'types' => ShareTransactionType::cases(),
            'shareholders' => Shareholder::query()->with('entity')->orderBy('shareholder_number')->get(),
            'resolutions' => Resolution::query()->orderByDesc('created_at')->limit(100)->get(['id', 'resolution_number', 'title']),
            'contracts' => Contract::query()->orderByDesc('created_at')->limit(100)->get(['id', 'contract_number', 'title']),
        ]);
    }

    public function store(StoreShareTransactionRequest $request)
    {
        $data = $request->validated();

        // Gesamtkaufpreis automatisch aus Anzahl und Preis je Aktie (Abschnitt 79)
        if (empty($data['total_price']) && ! empty($data['price_per_share'])) {
            $data['total_price'] = Money::round(
                Money::mul((string) $data['share_count'], (string) $data['price_per_share']),
                2,
            );
        }

        $data['transaction_number'] = NumberSequenceService::next('AB', 5);
        $data['status'] = ShareTransactionStatus::Draft->value;
        $data['created_by'] = $request->user()?->id;

        $transaction = ShareTransaction::create($data);

        AuditService::log('share-transactions.created', $transaction, [], $data);

        return redirect()
            ->route('share-transactions.show', $transaction)
            ->with('success', sprintf('Aktienbewegung %s wurde als Entwurf erfasst.', $transaction->transaction_number));
    }

    public function show(ShareTransaction $shareTransaction)
    {
        $shareTransaction->load([
            'buyer.entity',
            'seller.entity',
            'resolution',
            'contract',
            'reversalOf',
            'documentLinks.document',
        ]);

        $reversals = ShareTransaction::query()
            ->where('reversal_of', $shareTransaction->id)
            ->get();

        return view('share-transactions.show', [
            'transaction' => $shareTransaction,
            'reversals' => $reversals,
        ]);
    }

    /** Wirksam setzen (Abschnitt 80, Berechtigung shares.finalize). */
    public function makeEffective(Request $request, ShareTransaction $shareTransaction)
    {
        $this->shareholding->makeEffective($shareTransaction, $request->user());

        return redirect()
            ->route('share-transactions.show', $shareTransaction)
            ->with('success', sprintf('Aktienbewegung %s ist jetzt wirksam.', $shareTransaction->transaction_number));
    }

    /** Storno: Gegenbuchung bzw. Statuswechsel, nie löschen (Abschnitt 121). */
    public function cancel(Request $request, ShareTransaction $shareTransaction)
    {
        $result = $this->shareholding->cancel($shareTransaction, $request->user());

        $message = $result->id === $shareTransaction->id
            ? sprintf('Aktienbewegung %s wurde storniert.', $shareTransaction->transaction_number)
            : sprintf(
                'Aktienbewegung %s wurde per Gegenbuchung %s storniert.',
                $shareTransaction->transaction_number,
                $result->transaction_number,
            );

        return redirect()
            ->route('share-transactions.show', $shareTransaction)
            ->with('success', $message);
    }
}
