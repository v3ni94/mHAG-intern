<?php

namespace App\Http\Controllers;

use App\Enums\BookingType;
use App\Enums\EntityType;
use App\Enums\PaymentOrigin;
use App\Http\Requests\Loans\CancelPaymentRequest;
use App\Http\Requests\Loans\StorePaymentRequest;
use App\Models\BankAccount;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\PaymentAllocationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Zahlungseingänge (Abschnitte 46-49 Masterprompt): Erfassung mit
 * Verrechnung, Storno nur mit Grund und Gegenbuchung, nie löschen.
 */
class PaymentController extends Controller
{
    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        return $user;
    }

    private function paymentFor(User $user, int|string $id, array $with = []): Payment
    {
        return Payment::with($with)
            ->whereHas('loan', fn ($q) => $q->visibleTo($user)->inCurrentView($user))
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $user = $this->currentUser($request);

        $filters = [
            'loan_id' => $request->query('loan_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'origin' => $request->query('origin'),
            'status' => $request->query('status'),
            'direction' => $request->query('direction'),
        ];

        $payments = Payment::with(['loan', 'payer', 'payee', 'payerBankAccount', 'payeeBankAccount'])
            ->whereHas('loan', fn ($q) => $q->visibleTo($user)->inCurrentView($user))
            ->when($filters['loan_id'], fn ($q, $id) => $q->where('loan_id', $id))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('payment_date', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('payment_date', '<=', $to))
            ->when($filters['origin'], fn ($q, $origin) => $q->where('origin', $origin))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['direction'], fn ($q, $direction) => $q->where('direction', $direction))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            // Auswahlliste des Filters folgt der Ansicht (Abschnitt 13)
            'loans' => Loan::visibleTo($user)->inCurrentView($user)
                ->orderBy('loan_number')->get(['id', 'loan_number', 'title']),
            'origins' => PaymentOrigin::cases(),
            'canRecord' => $user->can('payments.record'),
            'canCancel' => $user->can('payments.cancel'),
            // Datenschutz: IBAN nur für interne Rollen bzw. eigene Entities
            'canSeeAccounts' => $user->isInternal(),
            'visibleEntityIds' => $user->accessibleEntityIds()->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->currentUser($request);

        $loans = Loan::visibleTo($user)
            ->with(['lender', 'borrower'])
            ->orderBy('loan_number')
            ->get();

        // Bankkonten beider Seiten je Darlehen (Abschnitt 46): Standard ist
        // Darlehensnehmer zahlt an Darlehensgeber. Externe Benutzer sehen
        // ausschliesslich Konten ihrer eigenen Entities (Datenschutz).
        $entityIds = $loans->pluck('lender_entity_id')
            ->merge($loans->pluck('borrower_entity_id'))
            ->filter()->unique()->values();

        $accountQuery = BankAccount::query()
            ->whereIn('entity_id', $entityIds)
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->orderBy('id');

        if (! $user->isInternal()) {
            $accountQuery->whereIn('entity_id', $user->accessibleEntityIds()->all());
        }

        $accountsByEntity = $accountQuery->get()
            ->groupBy('entity_id')
            ->map(fn ($accounts) => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'label' => trim(sprintf(
                    '%s · %s · %s',
                    $a->bank_name ?: 'Bank ohne Angabe',
                    self::formatIban($a->iban),
                    $a->account_holder,
                ), ' ·'),
            ])->values())
            ->toArray();

        $loanParties = [];
        foreach ($loans as $loan) {
            $loanParties[$loan->id] = [
                'payer_entity_id' => $loan->borrower_entity_id,
                'payee_entity_id' => $loan->lender_entity_id,
                'payer_name' => $loan->borrower?->display_name,
                'payee_name' => $loan->lender?->display_name,
                'payer_url' => $this->entityUrl($loan->borrower),
                'payee_url' => $this->entityUrl($loan->lender),
            ];
        }

        return view('payments.create', [
            'loans' => $loans,
            'selectedLoanId' => $request->query('loan_id'),
            'accountsByEntity' => $accountsByEntity,
            'loanParties' => $loanParties,
        ]);
    }

    /** IBAN lesbar in Vierergruppen. */
    public static function formatIban(?string $iban): string
    {
        $clean = strtoupper(preg_replace('/\s+/', '', (string) $iban));

        return $clean === '' ? '' : implode(' ', str_split($clean, 4));
    }

    /** Link auf die Akte der Partei (Reiter Bankkonten), falls verfügbar. */
    private function entityUrl(?Entity $entity): ?string
    {
        if (! $entity) {
            return null;
        }

        $routeName = $entity->type === EntityType::Person ? 'persons.show' : 'companies.show';
        if (! Route::has($routeName)) {
            return null;
        }

        return route($routeName, [$entity->id, 'tab' => 'bankkonten']);
    }

    public function store(
        StorePaymentRequest $request,
        PaymentAllocationService $allocationService,
        LoanRecalculationService $recalculationService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $data = $request->validated();

        $loan = Loan::visibleTo($user)->findOrFail($data['loan_id']);

        // Standard: Darlehensnehmer zahlt an Darlehensgeber (eingehend)
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'payer_entity_id' => $data['payer_entity_id'] ?? $loan->borrower_entity_id,
            'payee_entity_id' => $data['payee_entity_id'] ?? $loan->lender_entity_id,
            'payment_date' => $data['payment_date'],
            'value_date' => $data['value_date'] ?? null,
            'amount' => $data['amount'],
            'direction' => $data['direction'],
            'purpose' => $data['purpose'] ?? null,
            'reference' => $data['reference'] ?? null,
            'origin' => $data['origin'],
            'status' => 'recorded',
            'note' => $data['note'] ?? null,
            // Beide Kontoseiten (Abschnitt 46); die Zugehörigkeit zur Partei
            // wurde im FormRequest geprüft.
            'payer_bank_account_id' => $data['payer_bank_account_id'] ?? null,
            'payee_bank_account_id' => $data['payee_bank_account_id'] ?? null,
        ]);

        // Verrechnung: konfigurierte Reihenfolge oder manuelle Aufteilung (Abschnitt 47)
        $manualBuckets = null;
        if ($request->boolean('allocate_manually')) {
            $manualBuckets = array_filter(
                (array) ($data['alloc'] ?? []),
                fn ($v) => $v !== null && $v !== '' && Money::isPositive($v),
            );
        }
        $allocation = $allocationService->allocate($payment, $manualBuckets);

        AuditService::log('payments.recorded', $payment, [], [
            'loan_number' => $loan->loan_number,
            'amount' => (string) $payment->amount,
            'payment_date' => $payment->payment_date?->toDateString(),
            'origin' => $payment->origin?->value,
            'allocation' => $allocation,
        ]);

        // Neuberechnung ab dem Wirkungsdatum der Zahlung (Abschnitt 35):
        // eine Kapitalverrechnung verändert den Kapitalverlauf, die künftigen
        // Zins-SOLL-Zeilen müssen dem neuen Kapital folgen.
        $earliest = ($payment->value_date && $payment->value_date->lt($payment->payment_date))
            ? $payment->value_date
            : $payment->payment_date;

        $recalculationService->recalculate(
            $loan,
            'payments.recorded',
            $earliest ? Carbon::parse($earliest) : null,
            $user,
        );

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Zahlung über '.format_money($payment->amount).' wurde erfasst und verrechnet.');
    }

    public function show(Request $request, int $payment): View
    {
        $user = $this->currentUser($request);

        $model = $this->paymentFor($user, $payment, [
            'loan.lender', 'loan.borrower',
            'payer', 'payee', 'bankAccount',
            'payerBankAccount', 'payeeBankAccount',
            'allocations.repaymentPlanItem',
        ]);

        $transactions = LoanTransaction::query()
            ->where('source_type', $model->getMorphClass())
            ->where('source_id', $model->getKey())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        return view('payments.show', [
            'payment' => $model,
            'transactions' => $transactions,
            'canCancel' => $user->can('payments.cancel'),
            'canSeeAccounts' => $user->isInternal(),
            'visibleEntityIds' => $user->accessibleEntityIds()->all(),
        ]);
    }

    /**
     * Storno (Abschnitt 49): Status cancelled, Gegenbuchungen im
     * Darlehenskonto (reversal_of), danach Neuberechnung. KEIN Löschen.
     */
    public function cancel(
        CancelPaymentRequest $request,
        int $payment,
        LoanRecalculationService $recalculationService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $model = $this->paymentFor($user, $payment, ['loan']);

        if ($model->status === 'cancelled') {
            return back()->with('info', 'Diese Zahlung ist bereits storniert.');
        }

        $reason = $request->validated('cancel_reason');

        DB::transaction(function () use ($model, $user, $reason) {
            $model->update([
                'status' => 'cancelled',
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // Gegenbuchungen: jede aus der Zahlung entstandene Kontobuchung neutralisieren
            $transactions = LoanTransaction::query()
                ->where('loan_id', $model->loan_id)
                ->where('source_type', $model->getMorphClass())
                ->where('source_id', $model->getKey())
                ->where('booking_type', '!=', BookingType::Cancellation->value)
                ->whereNull('reversal_of')
                ->get();

            foreach ($transactions as $transaction) {
                LoanTransaction::create([
                    'loan_id' => $model->loan_id,
                    'booking_type' => BookingType::Cancellation,
                    'booking_date' => now()->toDateString(),
                    'effective_date' => $transaction->effective_date,
                    'amount' => Money::negate($transaction->amount),
                    'description' => 'Storno: '.($transaction->description ?: $transaction->booking_type->label()).' (Grund: '.$reason.')',
                    'source_type' => $model->getMorphClass(),
                    'source_id' => $model->getKey(),
                    'reversal_of' => $transaction->id,
                    'created_by' => $user->id,
                ]);
            }
        });

        AuditService::log('payments.cancelled', $model,
            ['status' => 'recorded'],
            ['status' => 'cancelled'],
            ['cancel_reason' => $reason, 'amount' => (string) $model->amount],
        );

        $recalculationService->recalculate(
            $model->loan,
            'payments.cancelled',
            $model->payment_date ? Carbon::parse($model->payment_date) : null,
            $user,
        );

        return redirect()
            ->route('payments.show', $model)
            ->with('success', 'Zahlung wurde storniert; Gegenbuchungen wurden erstellt.');
    }
}
