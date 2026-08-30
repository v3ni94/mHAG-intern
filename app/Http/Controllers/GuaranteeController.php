<?php

namespace App\Http\Controllers;

use App\Http\Requests\Loans\StoreGuaranteeRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bürgschaften (Abschnitt 67 Masterprompt): mehrere Bürgen je Darlehen.
 */
class GuaranteeController extends Controller
{
    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        return $user;
    }

    public function store(StoreGuaranteeRequest $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);

        $guarantee = $model->guarantees()->create($request->validated());

        AuditService::log('loans.guarantee_added', $guarantee, [], $request->validated(), ['loan_number' => $model->loan_number]);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'sicherheiten'])
            ->with('success', 'Bürgschaft wurde angelegt.');
    }

    public function update(StoreGuaranteeRequest $request, int $loan, int $guarantee): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $guaranteeModel = $model->guarantees()->findOrFail($guarantee);

        $old = $guaranteeModel->only(['guarantor_entity_id', 'guarantee_type', 'max_amount', 'valid_from', 'valid_until', 'status']);
        $guaranteeModel->update($request->validated());

        AuditService::log('loans.guarantee_updated', $guaranteeModel, $old, $request->validated(), ['loan_number' => $model->loan_number]);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'sicherheiten'])
            ->with('success', 'Bürgschaft wurde aktualisiert.');
    }

    public function destroy(Request $request, int $loan, int $guarantee): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $guaranteeModel = $model->guarantees()->findOrFail($guarantee);

        $old = $guaranteeModel->only(['guarantor_entity_id', 'guarantee_type', 'max_amount', 'valid_from', 'valid_until', 'status']);
        $guaranteeModel->delete();

        AuditService::log('loans.guarantee_removed', $model, $old, [], ['loan_number' => $model->loan_number]);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'sicherheiten'])
            ->with('success', 'Bürgschaft wurde entfernt.');
    }
}
