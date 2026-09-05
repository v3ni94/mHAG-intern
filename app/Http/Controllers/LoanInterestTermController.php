<?php

namespace App\Http\Controllers;

use App\Http\Requests\Loans\StoreInterestTermRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Zinssatz-Staffel (Abschnitt 40 Masterprompt): historisierte Zinssätze
 * je Darlehen; jede Änderung löst die Neuberechnung ab "gültig ab" aus.
 */
class LoanInterestTermController extends Controller
{
    public function __construct(private readonly LoanRecalculationService $recalculation) {}

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'entityAssignments.entity']);

        return $user;
    }

    public function store(StoreInterestTermRequest $request, int $loan): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $data = $request->validated();

        // Offene Vorgängerstaffel sauber abgrenzen: bis zum Vortag begrenzen
        $validFrom = Carbon::parse($data['valid_from']);
        $model->interestTerms()
            ->whereNull('valid_until')
            ->whereDate('valid_from', '<', $validFrom->toDateString())
            ->update(['valid_until' => $validFrom->copy()->subDay()->toDateString()]);

        $term = $model->interestTerms()->create($data);

        AuditService::log('loans.interest_term_added', $term, [], [
            'rate' => (string) $term->rate,
            'valid_from' => $term->valid_from?->toDateString(),
            'valid_until' => $term->valid_until?->toDateString(),
        ], ['loan_number' => $model->loan_number]);

        $this->recalculation->recalculate($model, 'interest_terms.changed', $validFrom, $user);

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'uebersicht'])
            ->with('success', 'Zinssatz '.format_percent($term->rate).' ab '.format_date($term->valid_from).' wurde erfasst.');
    }

    public function destroy(Request $request, int $loan, int $term): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Loan::visibleTo($user)->findOrFail($loan);
        $termModel = $model->interestTerms()->findOrFail($term);

        if ($model->interestTerms()->count() <= 1) {
            return back()->with('danger', 'Die letzte Zinssatz-Zeile kann nicht entfernt werden. Für zinslose Darlehen bitte einen Satz von 0 % erfassen.');
        }

        $old = [
            'rate' => (string) $termModel->rate,
            'valid_from' => $termModel->valid_from?->toDateString(),
            'valid_until' => $termModel->valid_until?->toDateString(),
        ];
        $validFrom = $termModel->valid_from;
        $termModel->delete();

        AuditService::log('loans.interest_term_removed', $model, $old, [], ['loan_number' => $model->loan_number]);

        $this->recalculation->recalculate(
            $model,
            'interest_terms.changed',
            $validFrom ? Carbon::parse($validFrom) : null,
            $user,
        );

        return redirect()
            ->route('loans.show', [$model, 'tab' => 'uebersicht'])
            ->with('success', 'Zinssatz-Zeile wurde entfernt.');
    }
}
