<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\Loans\LoanScheduleService;
use Illuminate\Console\Command;

/**
 * Fällige Zahlungsplan-Positionen fortschreiben (Abschnitt 24).
 *
 * Grundannahme planmäßiger Vertragserfüllung: Eine Position, deren
 * Fälligkeit erreicht ist und zu der keine Abweichung erfasst wurde, gilt als
 * systemseitig angenommen erfüllt, deutlich gekennzeichnet über die Herkunft.
 *
 * Bis zur Einrichtung eines täglichen Laufs geschah das nur, wenn jemand eine
 * Neuberechnung am Darlehen auslöste. Positionen blieben deshalb auf
 * "Geplant" stehen, obwohl ihre Fälligkeit längst erreicht war.
 *
 * Der Lauf ändert keine Beträge und erzeugt keine Buchung. Er setzt
 * ausschließlich den Zustand fortgeschriebener SOLL-Positionen und lässt
 * jede Position mit erfasstem IST unberührt. Er ist wiederholbar: ein
 * zweiter Lauf am selben Tag findet nichts mehr vor.
 */
class RollForwardSchedules extends Command
{
    protected $signature = 'app:roll-forward-schedules';

    protected $description = 'Schreibt fällige Zahlungsplan-Positionen auf "systemseitig angenommen" fort.';

    /** Status, in denen ein Darlehen laufende Zahlungsplan-Positionen hat. */
    private const RELEVANTE_STATUS = [
        LoanStatus::Active,
        LoanStatus::PartiallyRepaid,
        LoanStatus::Deferred,
        LoanStatus::Overdue,
        LoanStatus::Dunning,
        LoanStatus::Legal,
    ];

    public function handle(LoanScheduleService $schedule): int
    {
        $stichtag = today();
        $betroffen = 0;
        $darlehen = 0;

        Loan::query()
            ->whereIn('status', array_map(fn (LoanStatus $s) => $s->value, self::RELEVANTE_STATUS))
            ->orderBy('id')
            ->chunkById(100, function ($teil) use ($schedule, $stichtag, &$betroffen, &$darlehen) {
                foreach ($teil as $loan) {
                    $offen = $loan->repaymentPlanItems()
                        ->where('status', \App\Enums\RepaymentItemStatus::Planned->value)
                        ->whereDate('due_date', '<=', $stichtag->toDateString())
                        ->count();

                    if ($offen === 0) {
                        continue;
                    }

                    $schedule->rollForwardAssumed($loan, $stichtag);
                    $betroffen += $offen;
                    $darlehen++;
                }
            });

        $this->info(sprintf(
            '%d Position(en) in %d Darlehen fortgeschrieben (Stichtag %s).',
            $betroffen,
            $darlehen,
            $stichtag->format('d.m.Y'),
        ));

        return self::SUCCESS;
    }
}
