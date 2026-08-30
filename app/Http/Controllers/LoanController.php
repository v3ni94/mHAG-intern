<?php

namespace App\Http\Controllers;

use App\Enums\InterestDueDayMode;
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
use App\Services\Loans\DefaultInterestService;
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

    /**
     * Statuswechsel mit finanzieller Wirkung (Abschnitt 35): danach ist die
     * Neuberechnung anzustoßen, insbesondere bei Stundung (deferred).
     */
    /** Reiter der Detailseite (Abschnitt 135); Reihenfolge wie in der View. */
    public const TABS = [
        'uebersicht', 'konto', 'zahlungsplan', 'soll-ist', 'zahlungen', 'zinsen',
        'ertrag', 'gebuehren', 'auszahlungen', 'vertraege', 'sicherheiten',
        'dokumente', 'chronik', 'neuberechnungen',
    ];

    public const FINANCIAL_STATUSES = [
        'active', 'partially_repaid', 'repaid', 'deferred', 'terminated',
        'overdue', 'dunning', 'legal', 'defaulted', 'written_off',
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

    /** Sortierbare Spalten der Darlehensliste. */
    public const SORTABLE = ['loan_number', 'principal_amount', 'account_balance'];

    public function index(Request $request, LoanBalanceService $balanceService): View
    {
        $user = $this->currentUser($request);

        $filters = [
            'status' => $request->query('status'),
            'lender_entity_id' => $request->query('lender_entity_id'),
            'borrower_entity_id' => $request->query('borrower_entity_id'),
            'q' => trim((string) $request->query('q')),
        ];

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? (string) $request->query('sort')
            : 'loan_number';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

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
            ->when(
                $sort === 'account_balance',
                // Sortiert wird in der Datenbank, angezeigt wird der mit BCMath
                // exakt gebildete Wert. Fuer die Reihenfolge genuegt die
                // Summierung in der Datenbank.
                fn ($q) => $q->orderByRaw(
                    '(select coalesce(sum(amount), 0) from loan_transactions'
                    .' where loan_transactions.loan_id = loans.id'
                    .' and loan_transactions.effective_date <= ?) '.$direction,
                    [today()->toDateString()],
                ),
                fn ($q) => $q->orderBy($sort, $direction),
            )
            ->paginate(25)
            ->withQueryString();

        return view('loans.index', [
            'loans' => $loans,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            // Kontostaende aller angezeigten Darlehen in einer Abfrage
            'accountBalances' => $balanceService->accountBalancesFor(
                $loans->pluck('id')->all(),
            ),
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
                'interest_due_day_mode' => ($data['interest_due_day_mode'] ?? null) ?: InterestDueDayMode::EffectiveFrom->value,
                'interest_due_day' => ($data['interest_due_day_mode'] ?? null) === InterestDueDayMode::FixedDay->value
                    ? ($data['interest_due_day'] ?? null)
                    : null,
                'interest_due_month' => $data['interest_due_month'] ?? null,
                'interest_capitalization' => (bool) ($data['interest_capitalization'] ?? false),
                'interest_capitalization_from' => $data['interest_capitalization_from'] ?? null,
                'repayment_model' => $data['repayment_model'],
                'default_interest_enabled' => (bool) ($data['default_interest_enabled'] ?? false),
                'default_interest_rate' => $data['default_interest_rate'] ?? null,
                'default_interest_start' => $data['default_interest_start'] ?? null,
                'default_interest_basis' => ($data['default_interest_basis'] ?? null) ?: DefaultInterestService::BASIS_OVERDUE_TOTAL,
                'default_interest_method' => $data['default_interest_method'] ?? null,
                'default_interest_mode' => ($data['default_interest_mode'] ?? null) ?: DefaultInterestService::MODE_MANUAL,
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

        // Auszahlungen (Abschnitt 31): beliebig viele Teilauszahlungen mit
        // Datum, Betrag und Status. Bestätigte Zeilen werden geplant UND
        // bestätigt, damit die Kapitalbuchung mit Wirkungsdatum = Auszahlungs-
        // datum entsteht und die Zinsen dem Kapitalverlauf taggenau folgen.
        // planMany führt genau EINE Neuberechnung am Ende aus.
        $rows = $this->disbursementRows($data);
        if ($rows !== []) {
            $disbursementService->planMany($loan, $rows, $user);
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
            'disbursements' => count($rows),
        ]);

        // Hinweis statt harter Ablehnung: Auszahlung vor Wirkungsbeginn
        $beforeStart = collect($rows)->filter(
            fn (array $row) => $row['planned_date'] < $loan->effective_from->toDateString(),
        )->count();

        $redirect = redirect()
            ->route('loans.show', $loan)
            ->with('success', 'Darlehen '.$loan->loan_number.' wurde angelegt.'
                .($rows !== [] ? ' '.count($rows).' Auszahlung(en) wurden erfasst.' : ''));

        if ($beforeStart > 0) {
            $redirect->with('info', 'Hinweis: '.$beforeStart.' Auszahlung(en) liegen vor dem Wirkungsbeginn '
                .format_date($loan->effective_from).'. Bitte prüfen, ob Wirkungsbeginn oder Auszahlungsdatum zu korrigieren ist.');
        }

        return $redirect;
    }

    /**
     * Auszahlungszeilen des Anlegen-Formulars in die Struktur des
     * DisbursementService überführen (Abschnitt 31).
     *
     * @return array<int, array<string, mixed>>
     */
    private function disbursementRows(array $data): array
    {
        $rows = [];
        foreach ((array) ($data['disbursements'] ?? []) as $row) {
            if (empty($row['date']) || empty($row['amount'])) {
                continue;
            }
            $rows[] = [
                'planned_amount' => Money::normalize($row['amount']),
                'planned_date' => Carbon::parse($row['date'])->toDateString(),
                'confirmed' => ($row['status'] ?? 'planned') === 'confirmed',
                'origin' => $row['origin'] ?? \App\Enums\PaymentOrigin::ManualEntered->value,
                'reference' => $row['reference'] ?? null,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['planned_date'], $b['planned_date']));

        return $rows;
    }

    public function show(
        Request $request,
        int $loan,
        LoanBalanceService $balanceService,
        DefaultInterestService $defaultInterestService,
    ): View {
        $user = $this->currentUser($request);
        $tab = (string) $request->query('tab', 'uebersicht');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'uebersicht';
        }

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
            'auszahlungen' => ['disbursements.sourceBankAccount', 'disbursements.targetBankAccount', 'disbursements.bankAccount'],
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
            'uebersicht' => [
                // Verzugszinsen (Abschnitt 44): berechneter Stand zum heutigen
                // Tag und die fehlenden fachlichen Vorgaben für den Hinweis.
                'defaultInterest' => $defaultInterestService->calculate($model),
                'defaultInterestBooked' => $balances['default_interest'] ?? '0.00',
                'defaultInterestBasisLabel' => $defaultInterestService->basisLabel($model),
                'defaultInterestModeLabel' => $defaultInterestService->modeLabel($model),
            ],
            // Ertrag und Rendite (Anforderung 30.08.2026): jede Kennzahl mit
            // ihren Bestandteilen, damit der Rechenweg angezeigt werden kann.
            'ertrag' => ['yield' => app(\App\Services\Loans\LoanYieldService::class)->analyse($model)],
            'dokumente' => ['statementDocuments' => $this->statementDocuments($model)],
            'auszahlungen' => [
                // Bankkonten beider Seiten (Abschnitt 31): Geber- und Nehmerkonten
                'lenderAccounts' => $this->accountsOf($model->lender_entity_id, $user),
                'borrowerAccounts' => $this->accountsOf($model->borrower_entity_id, $user),
                'canSeeAccounts' => $user->isInternal(),
                'visibleEntityIds' => $user->accessibleEntityIds()->all(),
            ],
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

        // Pflichtfelder mit Vorgabewert nie auf null setzen (Spalten sind NOT NULL)
        foreach (['default_interest_basis' => DefaultInterestService::BASIS_OVERDUE_TOTAL,
            'default_interest_mode' => DefaultInterestService::MODE_MANUAL,
            'interest_due_day_mode' => InterestDueDayMode::EffectiveFrom->value] as $field => $fallback) {
            if (array_key_exists($field, $data) && ! $data[$field]) {
                $data[$field] = $fallback;
            }
        }

        $financeFields = [
            'principal_amount', 'credit_limit', 'effective_from', 'due_date', 'contract_end',
            'term_months', 'interest_method', 'interest_frequency', 'repayment_model',
            'interest_due_day_mode', 'interest_due_day', 'interest_due_month',
            'interest_capitalization', 'interest_capitalization_from',
            'default_interest_enabled', 'default_interest_rate', 'default_interest_start',
            'default_interest_basis', 'default_interest_method', 'default_interest_mode',
            'disbursement_date',
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
    public function transition(
        TransitionLoanRequest $request,
        int $loan,
        LoanRecalculationService $recalculationService,
    ): RedirectResponse {
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

        // Neuberechnung nach finanzwirksamen Statuswechseln (Abschnitt 35),
        // insbesondere bei Stundung. Frühestes betroffenes Datum ist das
        // Wirkungsdatum des Wechsels, sonst der Wirkungsbeginn.
        if (in_array($to->value, self::FINANCIAL_STATUSES, true)) {
            $earliest = $effectiveDate ?: $model->effective_from;
            $recalculationService->recalculate(
                $model,
                'loans.status_changed',
                $earliest ? Carbon::parse($earliest) : null,
                $user,
            );
        }

        return redirect()
            ->route('loans.show', $model)
            ->with('success', 'Status wurde auf "'.$to->label().'" geändert.');
    }

    /**
     * Ausfall erfassen (Anforderung 30.08.2026).
     *
     * Ausfalldatum ist ein Wirkungsdatum: ab diesem Tag entstehen keine
     * weiteren Soll-Zinsen. Ein Abschreibungsbetrag ist freiwillig; ohne ihn
     * bleibt die Forderung bestehen und nur der Status aendert sich. Es findet
     * keine rechtliche Bewertung und keine Einstufung als uneinbringlich statt.
     */
    public function recordDefault(
        \App\Http\Requests\Loans\RecordLoanDefaultRequest $request,
        int $loan,
        \App\Services\Loans\LoanDefaultService $defaultService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);
        $data = $request->validated();

        $writeOff = $data['write_off_amount'] ?? null;
        $result = $defaultService->record(
            $model,
            Carbon::parse($data['defaulted_on']),
            $data['reason'],
            $writeOff !== null ? (string) $writeOff : null,
            $user,
        );

        $meldung = 'Ausfall wurde zum '.format_date($data['defaulted_on']).' erfasst. '
            .'Ab diesem Tag entstehen keine weiteren Soll-Zinsen.';
        if ($result['write_off']) {
            $meldung .= ' Die Abschreibung über '.format_money($writeOff).' wurde gebucht.';
        }
        $meldung .= ' Die Erfassung ist eine Arbeitsangabe ohne rechtliche Bewertung;'
            .' eine Freigabe durch die Geschäftsführung ist einzuholen.';

        return redirect()
            ->route('loans.show', $model)
            ->with('success', $meldung);
    }

    /**
     * Ausfall zurücknehmen. Buchungen bleiben erhalten; die Abschreibung wird
     * auf Wunsch per Gegenbuchung aufgehoben, niemals gelöscht.
     */
    public function revokeDefault(
        Request $request,
        int $loan,
        \App\Services\Loans\LoanDefaultService $defaultService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'reverse_write_off' => ['nullable', 'boolean'],
        ], [], ['note' => 'Notiz']);

        if ($model->defaulted_on === null && $model->status !== LoanStatus::Defaulted) {
            return back()->with('danger', 'Für dieses Darlehen ist kein Ausfall erfasst.');
        }

        $reversals = $defaultService->revoke(
            $model,
            $request->boolean('reverse_write_off'),
            $validated['note'] ?? null,
            $user,
        );

        $meldung = 'Der Ausfall wurde zurückgenommen, die Soll-Zinsen laufen wieder.';
        if ($reversals > 0) {
            $meldung .= ' '.$reversals.' Abschreibung'.($reversals === 1 ? '' : 'en').' wurde'
                .($reversals === 1 ? '' : 'n').' per Gegenbuchung aufgehoben.';
        }

        return redirect()->route('loans.show', $model)->with('success', $meldung);
    }

    /**
     * Manuelle Neuberechnung (Abschnitt 36): Button auf der Detailseite.
     *
     * Zusätzlich (Abschnitt 44) die ausdrückliche Berechnung und Buchung der
     * Verzugszinsen zum gewählten Stichtag über dasselbe Formular
     * (Feld book_default_interest, optional default_interest_as_of).
     * Ohne erfassten Satz und ohne Verzugsbeginn wird nichts berechnet.
     */
    public function recalculate(
        Request $request,
        int $loan,
        LoanRecalculationService $recalculationService,
        DefaultInterestService $defaultInterestService,
    ): RedirectResponse {
        $user = $this->currentUser($request);
        $model = $this->loanFor($user, $loan);

        $validated = $request->validate(
            [
                'book_default_interest' => ['nullable', 'boolean'],
                'default_interest_as_of' => ['nullable', 'date'],
            ],
            ['default_interest_as_of.date' => 'Der Stichtag für die Verzugszinsen muss ein gültiges Datum sein.'],
        );

        // Verzugszinsen ausdrücklich berechnen und buchen
        if ($request->boolean('book_default_interest')) {
            $missing = $defaultInterestService->missingRequirements($model);
            if ($missing !== []) {
                return back()->with('danger', 'Verzugszinsen wurden nicht berechnet. '.implode(' ', $missing));
            }

            $asOf = ! empty($validated['default_interest_as_of'])
                ? Carbon::parse($validated['default_interest_as_of'])
                : Carbon::today();

            $calculation = $defaultInterestService->calculate($model, $asOf);
            $defaultInterestService->book($model, $asOf, $user);

            $recalculationService->recalculate(
                $model,
                'default_interest.booked',
                $model->default_interest_start ? Carbon::parse($model->default_interest_start) : null,
                $user,
            );

            return redirect()
                ->route('loans.show', [$model, 'tab' => 'konto'])
                ->with('success', 'Verzugszinsen zum '.format_date($asOf).' wurden berechnet: '
                    .format_money($calculation['amount']).'. Der Stand ist im Darlehenskonto gebucht.');
        }

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

    /**
     * Aktive Bankkonten einer Partei. Externe Benutzer sehen ausschließlich
     * Konten ihrer eigenen Entities (IBAN ist ein personenbezogenes Datum).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\BankAccount>
     */
    private function accountsOf(?int $entityId, User $user): \Illuminate\Support\Collection
    {
        if (! $entityId) {
            return collect();
        }
        if (! $user->isInternal() && ! $user->accessibleEntityIds()->contains($entityId)) {
            return collect();
        }

        return \App\Models\BankAccount::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Frühere Forderungsaufstellungen (Abschnitt 39): unveränderliche
     * Snapshots aus dem Dokumentenmodul, neueste zuerst.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Document>
     */
    private function statementDocuments(Loan $loan): \Illuminate\Support\Collection
    {
        return \App\Models\Document::query()
            ->where('category', LoanStatementController::SNAPSHOT_CATEGORY)
            ->whereHas('links', fn ($q) => $q
                ->where('linkable_type', $loan->getMorphClass())
                ->where('linkable_id', $loan->getKey()))
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();
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
