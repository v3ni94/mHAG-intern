<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ausfall eines Darlehens erfassen und zurücknehmen
 * (Anforderung vom 30.08.2026).
 *
 * Fachliche Festlegungen:
 *
 * 1. Erfasst werden Ausfalldatum (Wirkungsdatum), Grund und ein optionaler
 *    Abschreibungsbetrag. Ohne Betrag bleibt die Forderung bestehen und nur
 *    der Status ändert sich.
 * 2. Ab dem Ausfalldatum werden KEINE weiteren Soll-Zinsen erzeugt; bereits
 *    entstandene bleiben erhalten. Zinsen nach dem Ausfall wären eine
 *    Forderung, die das System nicht unterstellen darf.
 * 3. Der Abschreibungsbetrag wird als Buchung write_off mit Wirkungsdatum des
 *    Ausfalls erfasst und reduziert die Forderung.
 * 4. Der Ausfall ist rücknehmbar. Die Buchungen bleiben erhalten
 *    (append-only); die Abschreibung wird auf Wunsch per Gegenbuchung
 *    aufgehoben, nie gelöscht.
 * 5. Keine rechtliche Bewertung, keine automatische Einstufung als
 *    uneinbringlich (Masterprompt Abschnitt 133). Der Status ist eine
 *    Arbeitsangabe, keine Aussage über Werthaltigkeit oder Durchsetzbarkeit.
 */
class LoanDefaultService
{
    public function __construct(protected LoanRecalculationService $recalculation) {}

    /**
     * Ausfall erfassen.
     *
     * @param  ?string  $writeOffAmount  Abschreibungsbetrag als Dezimalstring, null = keine Abschreibung
     * @return array{write_off: ?LoanTransaction}
     */
    public function record(
        Loan $loan,
        CarbonInterface $defaultedOn,
        string $reason,
        ?string $writeOffAmount = null,
        ?User $user = null,
    ): array {
        $dateStr = Carbon::parse($defaultedOn->toDateString())->toDateString();
        $old = ['status' => $loan->status?->value, 'defaulted_on' => $loan->defaulted_on?->toDateString()];

        $transaction = DB::transaction(function () use ($loan, $dateStr, $reason, $writeOffAmount, $user) {
            $loan->update([
                'defaulted_on' => $dateStr,
                'default_reason' => $reason,
            ]);

            if ($loan->status !== LoanStatus::Defaulted) {
                $loan->transitionStatus(
                    LoanStatus::Defaulted,
                    $user,
                    'Ausfall erfasst: '.$reason,
                    Carbon::parse($dateStr),
                );
            }

            $writeOff = null;
            if ($writeOffAmount !== null && Money::isPositive($writeOffAmount)) {
                $writeOff = LoanTransaction::create([
                    'loan_id' => $loan->id,
                    'booking_type' => BookingType::WriteOff,
                    'booking_date' => today()->toDateString(),
                    'effective_date' => $dateStr,
                    // Forderungssicht: eine Abschreibung reduziert die Forderung
                    'amount' => Money::negate($writeOffAmount),
                    'description' => 'Abschreibung zum Ausfall vom '.format_date($dateStr),
                    'source_type' => $loan->getMorphClass(),
                    'source_id' => $loan->id,
                    'created_by' => $user?->id ?? auth()->id(),
                    'created_at' => now(),
                ]);
            }

            return $writeOff;
        });

        AuditService::log('loans.default_recorded', $loan, $old, [
            'status' => LoanStatus::Defaulted->value,
            'defaulted_on' => $dateStr,
        ], [
            'reason' => $reason,
            'write_off' => $writeOffAmount !== null ? Money::normalize($writeOffAmount) : null,
        ]);

        // Ab dem Ausfalldatum entstehen keine weiteren Soll-Zinsen: der
        // Zahlungsplan wird ab dem Wirkungsdatum neu aufgebaut.
        $this->recalculation->recalculate($loan->fresh(), 'loans.default_recorded', Carbon::parse($dateStr), $user);

        return ['write_off' => $transaction];
    }

    /**
     * Ausfall zurücknehmen. Der Status wird auf aktiv gesetzt, das
     * Ausfalldatum entfernt; die Soll-Zinsen laufen dadurch wieder weiter.
     *
     * @param  bool  $reverseWriteOff  Abschreibungen per Gegenbuchung aufheben
     * @return int Anzahl der Gegenbuchungen
     */
    public function revoke(Loan $loan, bool $reverseWriteOff, ?string $note = null, ?User $user = null): int
    {
        $old = ['status' => $loan->status?->value, 'defaulted_on' => $loan->defaulted_on?->toDateString()];

        $reversals = DB::transaction(function () use ($loan, $reverseWriteOff, $note, $user) {
            $count = 0;
            if ($reverseWriteOff) {
                $count = $this->reverseWriteOffs($loan, $user);
            }

            $loan->update(['defaulted_on' => null, 'default_reason' => null]);

            if ($loan->status === LoanStatus::Defaulted) {
                $loan->transitionStatus(
                    LoanStatus::Active,
                    $user,
                    $note ? 'Ausfall zurückgenommen: '.$note : 'Ausfall zurückgenommen',
                    today(),
                );
            }

            return $count;
        });

        AuditService::log('loans.default_revoked', $loan, $old, [
            'status' => LoanStatus::Active->value,
            'defaulted_on' => null,
        ], [
            'note' => $note,
            'reversed_write_offs' => $reversals,
        ]);

        $this->recalculation->recalculate($loan->fresh(), 'loans.default_revoked', null, $user);

        return $reversals;
    }

    /**
     * Eigene Abschreibungsbuchungen per Gegenbuchung aufheben, niemals
     * loeschen (Abschnitt 49). Bereits aufgehobene bleiben unberuehrt.
     */
    protected function reverseWriteOffs(Loan $loan, ?User $user): int
    {
        $own = LoanTransaction::query()
            ->where('loan_id', $loan->id)
            ->where('source_type', $loan->getMorphClass())
            ->where('source_id', $loan->id)
            ->whereIn('booking_type', [BookingType::WriteOff->value, BookingType::Cancellation->value])
            ->orderBy('id')
            ->get();

        $alreadyReversed = $own->pluck('reversal_of')->filter()->all();
        $count = 0;

        foreach ($own as $tx) {
            if ($tx->booking_type !== BookingType::WriteOff || in_array($tx->id, $alreadyReversed, true)) {
                continue;
            }

            LoanTransaction::create([
                'loan_id' => $loan->id,
                'booking_type' => BookingType::Cancellation,
                'booking_date' => today()->toDateString(),
                'effective_date' => $tx->effective_date->toDateString(),
                'amount' => Money::negate($tx->amount),
                'description' => 'Storno der Abschreibung (Ausfall zurückgenommen)',
                'source_type' => $loan->getMorphClass(),
                'source_id' => $loan->id,
                'reversal_of' => $tx->id,
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }
}
