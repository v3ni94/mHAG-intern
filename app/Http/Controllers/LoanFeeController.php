<?php

namespace App\Http\Controllers;

use App\Http\Requests\Loans\StoreLoanFeeRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Gebühren je Darlehen (Abschnitt 43 Masterprompt). Jede Änderung
 * löst die Neuberechnung aus (Abschnitt 35).
 */
class LoanFeeController extends Controller
{
    public function __construct(private readonly LoanRecalculationService $recalculation) {}

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        return $user;
    }

    public function store(StoreLoanFeeRequest $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);

        $fee = $model->fees()->create($request->validated());

        AuditService::log('loans.fee_added', $fee, [], $request->validated(), ['loan_number' => $model->loan_number]);

        $this->recalculate($model, $fee->due_date, $user);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'gebuehren'])
            ->with('success', 'Gebühr "'.$fee->name.'" wurde angelegt.');
    }

    public function update(StoreLoanFeeRequest $request, int $loan, int $fee): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $feeModel = $model->fees()->findOrFail($fee);

        $old = $feeModel->only(['type', 'name', 'amount', 'percentage', 'recurrence', 'due_date']);
        $feeModel->update($request->validated());

        AuditService::log('loans.fee_updated', $feeModel, $old, $request->validated(), ['loan_number' => $model->loan_number]);

        $this->recalculate($model, $feeModel->due_date, $user);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'gebuehren'])
            ->with('success', 'Gebühr wurde aktualisiert.');
    }

    public function destroy(Request $request, int $loan, int $fee): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $feeModel = $model->fees()->findOrFail($fee);

        $old = $feeModel->only(['type', 'name', 'amount', 'percentage', 'recurrence', 'due_date']);
        $dueDate = $feeModel->due_date;
        $feeModel->delete();

        AuditService::log('loans.fee_removed', $model, $old, [], ['loan_number' => $model->loan_number]);

        $this->recalculate($model, $dueDate, $user);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'gebuehren'])
            ->with('success', 'Gebühr wurde entfernt.');
    }

    private function recalculate(Loan $loan, mixed $dueDate, User $user): void
    {
        $earliest = $dueDate ?: $loan->effective_from;
        $this->recalculation->recalculate(
            $loan,
            'fees.changed',
            $earliest ? Carbon::parse($earliest) : null,
            $user,
        );
    }
}
