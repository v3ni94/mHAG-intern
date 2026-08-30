<?php

namespace App\Http\Controllers;

use App\Enums\PaymentOrigin;
use App\Http\Requests\Loans\ConfirmDisbursementRequest;
use App\Http\Requests\Loans\StoreDisbursementRequest;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\User;
use App\Services\Loans\DisbursementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Auszahlungen (Abschnitte 31/32 Masterprompt): planen, bestätigen,
 * als nicht erfolgt markieren, stornieren. Buchungen und Neuberechnung
 * laufen zentral über den DisbursementService.
 */
class DisbursementController extends Controller
{
    public function __construct(private readonly DisbursementService $disbursements)
    {
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        return $user;
    }

    private function disbursementFor(User $user, int|string $id): LoanDisbursement
    {
        return LoanDisbursement::with('loan')
            ->whereHas('loan', fn ($q) => $q->visibleTo($user))
            ->findOrFail($id);
    }

    public function store(StoreDisbursementRequest $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);

        $this->disbursements->plan($model, $request->validated(), $user);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'auszahlungen'])
            ->with('success', 'Auszahlung wurde geplant.');
    }

    public function confirm(ConfirmDisbursementRequest $request, int $disbursement): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = $this->disbursementFor($user, $disbursement);

        $this->disbursements->confirm(
            $model,
            $request->validated('actual_amount'),
            Carbon::parse($request->validated('actual_date')),
            PaymentOrigin::from($request->validated('origin')),
            $user,
        );

        return redirect()
            ->route('loans.show', [$model->loan, 'tab' => 'auszahlungen'])
            ->with('success', 'Auszahlung wurde bestätigt.');
    }

    /** Abschnitt 32: nicht erfolgte Auszahlung, IST 0, Folgewerte korrigieren. */
    public function fail(Request $request, int $disbursement): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = $this->disbursementFor($user, $disbursement);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        $this->disbursements->markFailed($model, $note, $user);

        return redirect()
            ->route('loans.show', [$model->loan, 'tab' => 'auszahlungen'])
            ->with('success', 'Auszahlung wurde als nicht erfolgt markiert.');
    }

    public function cancel(Request $request, int $disbursement): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = $this->disbursementFor($user, $disbursement);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']], [
            'reason.required' => 'Bitte einen Stornogrund angeben.',
        ])['reason'];

        $this->disbursements->cancel($model, $reason, $user);

        return redirect()
            ->route('loans.show', [$model->loan, 'tab' => 'auszahlungen'])
            ->with('success', 'Auszahlung wurde storniert.');
    }
}
