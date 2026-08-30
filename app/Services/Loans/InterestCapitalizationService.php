<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\RepaymentPlanItem;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Zinskapitalisierung: Zuschreibung fälliger Zinsen auf den valutierten
 * Betrag (Anforderung vom 30.08.2026).
 *
 * Fachliche Festlegungen:
 *
 * 1. Voraussetzung ist die ausdrückliche Einstellung am Darlehen
 *    (interest_capitalization). Ohne sie wird nichts zugeschrieben.
 * 2. Zugeschrieben werden nur Perioden, deren Fälligkeit erreicht ist und
 *    nicht vor dem Wirkungsdatum der Umstellung liegt
 *    (interest_capitalization_from, ohne Angabe der Wirkungsbeginn des
 *    Darlehens). Frühere Perioden bleiben unverändert.
 * 3. Die Zuschreibung wirkt zum Fälligkeitstag: die Buchung
 *    BookingType::InterestCapitalization erhöht das Kapital mit
 *    Wirkungsdatum des Fälligkeitstags. Damit ist der Saldo zu jedem
 *    Stichtag in sich geschlossen; die Folgeperioden verzinsen das erhöhte
 *    Kapital (Zinseszins), weil der Kapitalverlauf aus loan_transactions
 *    gebildet wird.
 * 4. Für die zugeschriebene Periode entsteht KEINE offene Zinsforderung.
 *    Die Planzeile erhält den Status "Dem Kapital zugeschrieben", zählt
 *    nicht als überfällig und ist damit auch von der Zahlungsverrechnung
 *    ausgenommen.
 * 5. Zeilen mit erfasstem IST oder manuell angepasste Zeilen werden NIE
 *    überschrieben. Eine bereits bestätigte Zinszahlung wird also nicht
 *    nachträglich in eine Zuschreibung verwandelt.
 *    Die zugeschriebene Planzeile ist der maßgebliche Nachweis der
 *    abgeschlossenen Periode und wird von der Plangenerierung als geschützt
 *    behandelt (Status weder planned noch assumed). Das ist wichtig, weil das
 *    Kapital am Fälligkeitstag steigt: eine erneute Berechnung derselben
 *    Periode würde den letzten Tag bereits auf dem erhöhten Kapital rechnen.
 *    Maßgeblich bleibt der gebuchte Betrag.
 * 6. Append-only: gebuchte Zuschreibungen werden nicht gelöscht. Wird die
 *    Einstellung abgeschaltet, entstehen nur keine neuen Zuschreibungen;
 *    bestehende bleiben und sind bei Bedarf per Gegenbuchung aufzuheben.
 *
 * Die Perioden kommen aus LoanScheduleService::interestPeriods, damit
 * Fälligkeitsraster und Zuschreibung nicht auseinanderlaufen können.
 */
class InterestCapitalizationService
{
    public function __construct(
        protected LoanScheduleService $schedule,
        protected InterestCalculationService $interest,
    ) {}

    /**
     * Fällige Zuschreibungen bis zum Stichtag buchen.
     *
     * @return array{booked: int, amount: string, periods: array<int, array{due: string, amount: string}>}
     */
    public function process(Loan $loan, ?CarbonInterface $asOf = null, ?User $user = null): array
    {
        $result = ['booked' => 0, 'amount' => '0.00', 'periods' => []];

        if (! $loan->interest_capitalization) {
            return $result;
        }

        $asOfStr = ($asOf ? Carbon::parse($asOf->toDateString()) : today())->toDateString();
        $from = ($loan->interest_capitalization_from ?? $loan->effective_from)->toDateString();

        // Aufsteigend, damit jede Zuschreibung das Kapital der Folgeperioden
        // erhoeht, bevor deren Zinsen berechnet werden (Zinseszins).
        foreach ($this->schedule->interestPeriods($loan) as $period) {
            if ($period['due'] > $asOfStr) {
                break; // noch nicht faellig
            }
            if ($period['due'] < $from) {
                continue; // vor dem Wirkungsdatum der Umstellung
            }

            $existing = $loan->repaymentPlanItems()
                ->where('item_type', RepaymentItemType::Interest->value)
                ->whereDate('due_date', $period['due'])
                ->orderBy('id')
                ->get();

            $open = $existing->first(fn (RepaymentPlanItem $i) => ! $i->manually_adjusted
                && in_array($i->status, [RepaymentItemStatus::Planned, RepaymentItemStatus::Assumed], true));

            // Bereits zugeschrieben, bestaetigt, erlassen oder manuell
            // angepasst: unberuehrt lassen.
            if ($existing->isNotEmpty() && $open === null) {
                continue;
            }

            $amount = $this->interest->interestForLoanPeriod(
                $loan,
                Carbon::parse($period['start']),
                Carbon::parse($period['end_excl']),
            );
            if (! Money::isPositive($amount)) {
                continue;
            }

            DB::transaction(function () use ($loan, $period, $amount, $open, $user) {
                $transaction = LoanTransaction::create([
                    'loan_id' => $loan->id,
                    'booking_type' => BookingType::InterestCapitalization,
                    'booking_date' => today()->toDateString(),
                    'effective_date' => $period['due'],
                    'amount' => $amount,
                    'description' => sprintf(
                        'Zinsen %s bis %s dem valutierten Betrag zugeschrieben',
                        format_date($period['start']),
                        format_date($period['due']),
                    ),
                    'source_type' => $loan->getMorphClass(),
                    'source_id' => $loan->id,
                    'created_by' => $user?->id ?? auth()->id(),
                    'created_at' => now(),
                ]);

                if ($open) {
                    $open->update([
                        'planned_amount' => $amount,
                        'status' => RepaymentItemStatus::Capitalized,
                        'actual_amount' => null,
                        'actual_date' => null,
                    ]);
                } else {
                    $loan->repaymentPlanItems()->create([
                        'item_type' => RepaymentItemType::Interest,
                        'due_date' => $period['due'],
                        'planned_amount' => $amount,
                        'status' => RepaymentItemStatus::Capitalized,
                        'origin' => PaymentOrigin::Assumed,
                    ]);
                }

                AuditService::log('loans.interest_capitalized', $loan, [], [
                    'period_from' => $period['start'],
                    'due' => $period['due'],
                    'amount' => $amount,
                    'transaction_id' => $transaction->id,
                ]);
            });

            $result['booked']++;
            $result['amount'] = Money::add($result['amount'], $amount);
            $result['periods'][] = ['due' => $period['due'], 'amount' => $amount];
        }

        return $result;
    }
}
