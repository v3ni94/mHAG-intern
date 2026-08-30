<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Http\Requests\Loans\TransitionLoanRequest;
use App\Http\Requests\Loans\UpdateLoanRequest;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\LoanScheduleService;
use App\Services\NumberSequenceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoanController extends Controller
{
    /**
     * Sinnvolle Folgestatus je Status (Abschnitt 21 Masterprompt).
     * Kein Anspruch auf rechtliche Bewertung, nur Arbeitsablauf.
     */
    public const TRANSITIONS = [
        'draft' => ['contract_prepared', 'for_signature', 'signed', 'active', 'archived'],
        'contract_prepared' => ['for_signature', 'signed', 'draft', 'archived'],
        'for_signature' => ['signed', 'contract_prepared', 'archived'],
        'signed' => ['disbursement_planned', 'active', 'archived'],
        'disbursement_planned' => ['active', 'signed', 'archived'],
        'active' => ['partially_repaid', 'repaid', 'deferred', 'terminated', 'overdue', 'dunning', 'legal', 'defaulted', 'written_off', 'archived'],
        'partially_repaid' => ['active', 'repaid', 'deferred', 'terminated', 'overdue', 'dunning', 'legal', 'defaulted', 'written_off', 'archived'],
        'repaid' => ['active', 'archived'],
        'deferred' => ['active', 'terminated', 'overdue', 'archived'],
        'terminated' => ['active', 'dunning', 'legal', 'repaid', 'defaulted', 'written_off', 'archived'],
        'overdue' => ['active', 'dunning', 'legal', 'deferred', 'defaulted', 'written_off', 'archived'],
        'dunning' => ['active', 'overdue', 'legal', 'defaulted', 'written_off', 'archived'],
        'legal' => ['active', 'defaulted', 'written_off', 'archived'],
        'defaulted' => ['legal', 'written_off', 'archived'],
        'written_off' => ['archived'],
        'archived' => ['draft', 'active'],
    ];

    /** Gescopter Zugriff: findOrFail immer über visibleTo (Abschnitt 14). */
    private function loanFor(User $user, int|string $id, array $with = []): Loan
    {
        return Loan::visibleTo($user)->with($with)->findOrFail($id);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        return $user;
    }

    public function index(Request $request): View
    {
        $user = $this->currentUser($request);

        $filters = [
            'status' => $request->query('status'),
            'lender_entity_id' => $request->query('lender_entity_id'),
            'borrower_entity_id' => $request->query('borrower_entity_id'),
            'q' => trim((string) $request->query('q')),
        ];

        $loans = Loan::visibleTo($user)
            ->with(['lender', 'borrower', 'loanType'])
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['lender_entity_id'], fn ($q, $id) => $q->where('lender_entity_id', $id))
            ->when($filters['borrower_entity_id'], fn ($q, $id) => $q->where('borrower_entity_id', $id))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('loan_number', 'like', '%'.$filters['q'].'%')
                        ->orWhere('title', 'like', '%'.$filters['q'].'%');
                });
            })
            ->orderByDesc('loan_number')
            ->paginate(25)
            ->withQueryString();

        return view('loans.index', [
            'loans' => $loans,
            'filters' => $filters,
            'statuses' => LoanStatus::cases(),
            'entities' => Entity::visibleTo($user)->orderBy('display_name')->get(['id', 'display_name']),
            'isInternal' => $user->isInternal(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->currentUser($request);

        return view('loans.create', [
            'loan' => new Loan([
                'currency' => 'EUR',
                'interest_method' => \App\Enums\InterestMethod::Act365,
                'interest_frequency' => \App\Enums\InterestFrequency::Monthly,
                'repayment_model' => \App\Enums\RepaymentModel::Bullet,
            ]),
            'entities' => Entity::visibleTo($user)->orderBy('display_name')->get(['id', 'display_name']),
            'loanTypes' => LoanType::where('is_active', true)->orderBy('name')->get(),
            'handlers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'isInternal' => $user->isInternal(),
        ]);
    }

    public function store(
        StoreLoanRequest $request,
        LoanScheduleService $scheduleService,
        LoanRecalculationService $recalculationService,
        DisbursementService $disbursementService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $data = $request->validated();

        $loan = DB::transaction(function () use ($data, $user) {
            $loan = Loan::create([
                'loan_number' => NumberSequenceService::next('DAR'),
                'title' => $data['title'],
                'lender_entity_id' => $data['lender_entity_id'],
                'borrower_entity_id' => $data['borrower_entity_id'],
                'loan_type_id' => $data['loan_type_id'] ?? null,
                'contract_basis' => $data['contract_basis'] ?? null,
                'contract_date' => $data['contract_date'] ?? null,
                'effective_from' => $data['effective_from'],
                'disbursement_date' => $data['disbursement_date'] ?? null,
                'term_months' => $data['term_months'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'notice_period' => $data['notice_period'] ?? null,
                'contract_end' => $data['contract_end'] ?? null,
                'principal_amount' => $data['principal_amount'],
                'credit_limit' => $data['credit_limit'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'EUR'),
                'interest_method' => $data['interest_method'],
                'interest_frequency' => $data['interest_frequency'],
                'repayment_model' => $data['repayment_model'],
                'default_interest_enabled' => (bool) ($data['default_interest_enabled'] ?? false),
                'default_interest_rate' => $data['default_interest_rate'] ?? null,
                'risk_rating' => $user->isInternal() ? ($data['risk_rating'] ?? null) : null,
                'handler_user_id' => $data['handler_user_id'] ?? null,
                'project' => $data['project'] ?? null,
                'cost_center' => $data['cost_center'] ?? null,
                'internal_notes' => $user->isInternal() ? ($data['internal_notes'] ?? null) : null,
                'status' => LoanStatus::Draft,
            ]);

            // Zinssatz als erste historisierte Staffelzeile ab Wirkungsbeginn (Abschnitt 40)
            $loan->interestTerms()->create([
                'rate' => $data['interest_rate'],
                'valid_from' => $data['effective_from'],
            ]);

            return $loan;
        });

        // Optional: Auszahlung direkt planen (Abschnitt 31)
        if ($request->boolean('plan_disbursement')) {
            $disbursementService->plan($loan, [
                'planned_amount' => $data['disbursement_planned_amount'],
                'planned_date' => $data['disbursement_planned_date'],
                'reference' => $data['disbursement_reference'] ?? null,
            ], $user);
        }

        // Zahlungsplan (SOLL) erzeugen; bei rückwirkender Erfassung (Abschnitt 33)
        // Vergangenheit als systemseitig angenommen fortschreiben und neu rechnen.
        $scheduleService->generate($loan);
        $effectiveFrom = Carbon::parse($data['effective_from']);
        if ($effectiveFrom->isPast() && ! $effectiveFrom->isToday()) {
            $scheduleService->rollForwardAssumed($loan);
            $recalculationService->recalculate($loan, 'loans.created_retroactively', $effectiveFrom, $user);
        }

        AuditService::log('loans.created', $loan, [], [
            'loan_number' => $loan->loan_number,
            'title' => $loan->title,
            'principal_amount' => (string) $loan->principal_amount,
            'effective_from' => $loan->effective_from?->toDateString(),
        ]);

        return redirect()
            ->route('loans.show', $loan)
            ->with('success', 'Darlehen '.$loan->loan_number.' wurde angelegt.');
    }

    public function show(Request $request, int $loan, LoanBalanceService $balanceService): View
    {
        $user = $this->currentUser($request);
        $tab = (string) $request->query('tab', 'uebersicht');

        $relations = [
            'lender', 'borrower', 'loanType', 'handler', 'interestTerms',
        ];
        $relations = array_merge($relations, match ($tab) {
            'konto' => ['transactions.creator'],
            'zahlungsplan' => ['repaymentPlanItems'],
            'soll-ist' => ['repaymentPlanItems'],
            'zahlungen' => ['payments.payer', 'payments.payee', 'payments.allocations'],
            'zinsen' => ['repaymentPlanItems'],
            'gebuehren' => ['fees'],
            'auszahlungen' => ['disbursements.bankAccount'],
            'vertraege' => ['contracts'],
            'sicherheiten' => ['securities.provider', 'guarantees.guarantor'],
            'dokumente' => ['documentLinks.document'],
            'chronik' => ['statusHistory.changedBy'],
            'neuberechnungen' => ['recalculations.triggeredBy'],
            default => [],
        });

        $model = $this->loanFor($user, $loan, $relations);
        $balances = $balanceService->balances($model);

        $data = [
            'loan' => $model,
            'tab' => $tab,
            'balances' => $balances,
            'isInternal' => $user->isInternal(),
            'currentRate' => $this->currentRate($model),
            'transitions' => $this->transitionOptions($model),
            'canUpdate' => $user->can('loans.update'),
            'canRecord' => $user->can('payments.record'),
            'canCancelPayments' => $user->can('payments.cancel'),
            'canArchive' => $user->can('loans.archive'),
        ];

        $data = array_merge($data, match ($tab) {
            'konto' => ['accountRows' => $this->accountRows($model)],
            'soll-ist' => ['interestItems' => $model->repaymentPlanItems->where('item_type', \App\Enums\RepaymentItemType::Interest)],
            'zinsen' => ['interestItems' => $model->repaymentPlanItems->where('item_type', \App\Enums\RepaymentItemType::Interest)],
            'sicherheiten' => ['entities' => Entity::visibleTo($user)->orderBy('display_name')->get(['id', 'display_name'])],
            'chronik' => ['auditLogs' => AuditLog::with('user')
                ->where('auditable_type', $model->getMorphClass())
                ->where('auditable_id', $model->getKey())
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()],
            default => [],
        });

        return view('loans.show', $data);
    }

    public function edit(Request $request, int $loan): View
    {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan, ['lender', 'borrower', 'loanType', 'handler', 'interestTerms']);

        return view('loans.edit', [
            'loan' => $model,
            'entities' => Entity::visibleTo($user)->orderBy('display_name')->get(['id', 'display_name']),
            'loanTypes' => LoanType::where('is_active', true)->orderBy('name')->get(),
            'handlers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'isInternal' => $user->isInternal(),
        ]);
    }

    public function update(
        UpdateLoanRequest $request,
        int $loan,
        LoanRecalculationService $recalculationService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);
        $data = $request->validated();

        if (! $user->isInternal()) {
            unset($data['risk_rating'], $data['internal_notes']);
        }

        $financeFields = [
            'principal_amount', 'credit_limit', 'effective_from', 'due_date', 'contract_end',
            'term_months', 'interest_method', 'interest_frequency', 'repayment_model',
            'default_interest_enabled', 'default_interest_rate', 'disbursement_date',
        ];

        $old = $model->only(array_keys($data));
        $model->fill($data);
        $dirty = array_keys($model->getDirty());
        $model->save();

        AuditService::log('loans.updated', $model, $old, $model->only($dirty));

        // Finanzrelevante Änderungen: ab frühestem betroffenen Datum neu rechnen (Abschnitt 35)
        if (array_intersect($dirty, $financeFields) !== []) {
            $recalculationService->recalculate(
                $model,
                'loans.updated',
                $model->effective_from ? Carbon::parse($model->effective_from) : null,
                $user,
            );
        }

        return redirect()
            ->route('loans.show', $model)
            ->with('success', 'Darlehen wurde aktualisiert.');
    }

    /** Statuswechsel (Abschnitt 21): immer über transitionStatus, mit Historie. */
    public function transition(TransitionLoanRequest $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);
        $to = LoanStatus::from($request->validated('status'));

        $allowed = self::TRANSITIONS[$model->status->value] ?? [];
        if (! in_array($to->value, $allowed, true)) {
            return back()->with('danger', 'Der Statuswechsel von "'.$model->status->label().'" nach "'.$to->label().'" ist nicht vorgesehen.');
        }

        $from = $model->status;
        $effectiveDate = $request->validated('effective_date');
        $model->transitionStatus(
            $to,
            $user,
            $request->validated('note'),
            $effectiveDate ? Carbon::parse($effectiveDate) : null,
        );

        AuditService::log('loans.status_changed', $model,
            ['status' => $from->value],
            ['status' => $to->value],
            ['note' => $request->validated('note')],
        );

        return redirect()
            ->route('loans.show', $model)
            ->with('success', 'Status wurde auf "'.$to->label().'" geändert.');
    }

    /** Manuelle Neuberechnung (Abschnitt 36): Button auf der Detailseite. */
    public function recalculate(
        Request $request,
        int $loan,
        LoanRecalculationService $recalculationService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);

        $recalculationService->recalculate(
            $model,
            'manual',
            $model->effective_from ? Carbon::parse($model->effective_from) : null,
            $user,
        );

        AuditService::log('loans.recalculation_triggered', $model);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'neuberechnungen'])
            ->with('success', 'Neuberechnung wurde ausgeführt.');
    }

    public function archive(Request $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);

        if ($model->status === LoanStatus::Archived) {
            return back()->with('info', 'Das Darlehen ist bereits archiviert.');
        }

        $from = $model->status;
        $model->transitionStatus(LoanStatus::Archived, $user, 'Archiviert über die Darlehensliste.');

        AuditService::log('loans.archived', $model, ['status' => $from->value], ['status' => LoanStatus::Archived->value]);

        return redirect()
            ->route('loans.index')
            ->with('success', 'Darlehen '.$model->loan_number.' wurde archiviert.');
    }

    /** Aktuell gültiger Zinssatz aus der Staffel (Anzeige im KPI-Block). */
    private function currentRate(Loan $loan): ?string
    {
        $today = now()->toDateString();

        return $loan->interestTerms
            ->filter(function ($term) use ($today) {
                $from = $term->valid_from?->toDateString();
                $until = $term->valid_until?->toDateString();

                return ($from === null || $from <= $today) && ($until === null || $until >= $today);
            })
            ->sortByDesc(fn ($term) => $term->valid_from?->toDateString())
            ->first()?->rate;
    }

    private function transitionOptions(Loan $loan): array
    {
        $allowed = self::TRANSITIONS[$loan->status->value] ?? [];

        return array_map(fn (string $status) => LoanStatus::from($status), $allowed);
    }

    /** Darlehenskonto (Abschnitt 48): chronologisch mit laufendem Saldo. */
    private function accountRows(Loan $loan): array
    {
        $rows = [];
        $saldo = '0.00';
        foreach ($loan->transactions as $transaction) {
            $saldo = Money::add($saldo, $transaction->amount);
            $rows[] = ['transaction' => $transaction, 'saldo' => $saldo];
        }

        return $rows;
    }
}
