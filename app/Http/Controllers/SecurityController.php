<?php

namespace App\Http\Controllers;

use App\Enums\SecurityType;
use App\Http\Requests\Loans\StoreSecurityRequest;
use App\Models\Guarantee;
use App\Models\Loan;
use App\Models\Security;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sicherheiten (Abschnitt 66 Masterprompt): globale Übersicht mit
 * Ablaufwarnung sowie Verwaltung je Darlehen.
 */
class SecurityController extends Controller
{
    /** Vorlaufzeit für die Ablaufwarnung (Tage). */
    public const EXPIRY_WARNING_DAYS = 90;

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
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'loan_id' => $request->query('loan_id'),
        ];

        $securities = Security::with(['loan', 'provider'])
            ->whereHas('loan', fn ($q) => $q->visibleTo($user))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['loan_id'], fn ($q, $id) => $q->where('loan_id', $id))
            ->orderByRaw('valid_until is null, valid_until')
            ->paginate(25, ['*'], 'securities_page')
            ->withQueryString();

        $guarantees = Guarantee::with(['loan', 'guarantor'])
            ->whereHas('loan', fn ($q) => $q->visibleTo($user))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['loan_id'], fn ($q, $id) => $q->where('loan_id', $id))
            ->orderByRaw('valid_until is null, valid_until')
            ->paginate(25, ['*'], 'guarantees_page')
            ->withQueryString();

        return view('securities.index', [
            'securities' => $securities,
            'guarantees' => $guarantees,
            'filters' => $filters,
            'types' => SecurityType::cases(),
            'loans' => Loan::visibleTo($user)->orderBy('loan_number')->get(['id', 'loan_number', 'title']),
            'warningDays' => self::EXPIRY_WARNING_DAYS,
        ]);
    }

    public function store(StoreSecurityRequest $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);

        $security = $model->securities()->create($request->validated());

        AuditService::log('loans.security_added', $security, [], $request->validated(), ['loan_number' => $model->loan_number]);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'sicherheiten'])
            ->with('success', 'Sicherheit wurde angelegt.');
    }

    public function update(StoreSecurityRequest $request, int $loan, int $security): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $securityModel = $model->securities()->findOrFail($security);

        $old = $securityModel->only(['provider_entity_id', 'type', 'nominal_value', 'internal_value', 'rank', 'valid_from', 'valid_until', 'status']);
        $securityModel->update($request->validated());

        AuditService::log('loans.security_updated', $securityModel, $old, $request->validated(), ['loan_number' => $model->loan_number]);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'sicherheiten'])
            ->with('success', 'Sicherheit wurde aktualisiert.');
    }

    public function destroy(Request $request, int $loan, int $security): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $securityModel = $model->securities()->findOrFail($security);

        $old = $securityModel->only(['provider_entity_id', 'type', 'nominal_value', 'internal_value', 'rank', 'valid_from', 'valid_until', 'status']);
        $securityModel->delete();

        AuditService::log('loans.security_removed', $model, $old, [], ['loan_number' => $model->loan_number]);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'sicherheiten'])
            ->with('success', 'Sicherheit wurde entfernt.');
    }
}
