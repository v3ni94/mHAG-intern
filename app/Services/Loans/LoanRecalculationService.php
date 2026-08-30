<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\DisbursementStatus;
use App\Enums\PaymentOrigin;
use App\Models\Loan;
use App\Models\LoanRecalculation;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Recalculation Engine (Masterprompt Abschnitte 35-38).
 *
 * Deterministisch: gleiche Eingangsdaten liefern immer dasselbe Ergebnis.
 * Ablauf: Snapshot alt (balances) -> Auszahlungen gem. Abschnitt 24 fortschreiben
 * -> Zahlungsplan neu erzeugen (generate, Zins-SOLL aus Kapitalverlauf)
 * -> rollForwardAssumed -> Snapshot neu -> Protokoll in loan_recalculations.
 * Fehler werden abgefangen und als status=error protokolliert.
 */
class LoanRecalculationService
{
    public function __construct(
        protected LoanScheduleService $schedule,
        protected LoanBalanceService $balance,
        protected DefaultInterestService $defaultInterest,
    ) {}

    public function recalculate(Loan $loan, string $trigger, ?CarbonInterface $earliestAffectedDate = null, ?User $user = null): LoanRecalculation
    {
        $startedAt = hrtime(true);
        $oldState = null;

        try {
            $oldState = $this->balance->balances($loan);

            DB::transaction(function () use ($loan, $user) {
                $this->rollForwardAssumedDisbursements($loan, $user);
                $this->schedule->generate($loan);
                $this->schedule->rollForwardAssumed($loan);
                $this->updateDefaultInterest($loan, $user);
            });

            $newState = $this->balance->balances($loan);

            return LoanRecalculation::create([
                'loan_id' => $loan->id,
                'trigger_action' => $trigger,
                'triggered_by' => $user?->id,
                'earliest_affected_date' => $earliestAffectedDate?->toDateString(),
                'old_state' => $oldState,
                'new_state' => $newState,
                'status' => 'ok',
                'error' => null,
                'duration_ms' => $this->elapsedMs($startedAt),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return LoanRecalculation::create([
                'loan_id' => $loan->id,
                'trigger_action' => $trigger,
                'triggered_by' => $user?->id,
                'earliest_affected_date' => $earliestAffectedDate?->toDateString(),
                'old_state' => $oldState,
                'new_state' => null,
                'status' => 'error',
                'error' => get_class($e).': '.$e->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Grundannahme Abschnitt 24 fuer Auszahlungen: geplante Auszahlungen mit
     * erreichtem Plandatum gelten als systemseitig angenommen und werden als
     * disbursement-Transaktion gebucht (Wirkungsdatum = Plandatum).
     * Erst dadurch entsteht der Kapitalverlauf fuer rueckwirkend erfasste
     * Vertraege (Abschnitt 33). Bereits angenommene/bestaetigte Auszahlungen
     * werden nicht erneut gebucht (append-only).
     */
    protected function rollForwardAssumedDisbursements(Loan $loan, ?User $user): void
    {
        $due = $loan->disbursements()
            ->where('status', DisbursementStatus::Planned->value)
            ->whereDate('planned_date', '<=', today()->toDateString())
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get();

        foreach ($due as $disbursement) {
            $disbursement->update([
                'status' => DisbursementStatus::Assumed,
                'origin' => PaymentOrigin::Assumed,
            ]);

            LoanTransaction::create([
                'loan_id' => $loan->id,
                'booking_type' => BookingType::Disbursement,
                'booking_date' => today()->toDateString(),
                'effective_date' => $disbursement->planned_date->toDateString(),
                'amount' => Money::normalize($disbursement->planned_amount),
                'description' => 'Auszahlung systemseitig angenommen (planmäßige Vertragserfüllung)',
                'source_type' => $disbursement->getMorphClass(),
                'source_id' => $disbursement->id,
                'created_by' => $user?->id,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Verzugszinsen fortschreiben (Abschnitte 36/44), ausschliesslich bei
     * Aktivierungsart "automatisch" UND vollstaendiger fachlicher Vorgabe
     * (Satz und Verzugsbeginn). Bei "manuell" bleibt die Buchung dem
     * ausdruecklichen Anstoss durch den Bearbeiter vorbehalten; ohne
     * Vorgaben wird nichts berechnet und nichts gebucht.
     */
    protected function updateDefaultInterest(Loan $loan, ?User $user): void
    {
        if ($loan->default_interest_mode !== DefaultInterestService::MODE_AUTOMATIC) {
            return;
        }
        if (! $this->defaultInterest->isConfigured($loan)) {
            return;
        }

        $this->defaultInterest->book($loan, today(), $user);
    }

    protected function elapsedMs(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
