<?php

namespace Tests\Feature\Loans;

use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Support\Carbon;

/**
 * Täglicher Lauf zur Fortschreibung fälliger Zahlungsplan-Positionen.
 *
 * Anlass: Der Zeitplan der Anwendung war hinterlegt, aber auf dem Webspace
 * lief kein Cronjob, der ihn anstößt. Fällige Positionen blieben deshalb auf
 * "Geplant" stehen, bis jemand von Hand eine Neuberechnung auslöste.
 *
 * Der Lauf ändert keine Beträge und erzeugt keine Buchung. Er setzt nur den
 * Zustand fortgeschriebener SOLL-Positionen.
 */
class EngineRollForwardCommandTest extends EngineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15 03:00:00'));
    }

    public function test_faellige_positionen_werden_fortgeschrieben(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        // Alles auf "Geplant" zuruecksetzen, wie ohne taeglichen Lauf.
        $loan->repaymentPlanItems()->update([
            'status' => RepaymentItemStatus::Planned->value,
            'actual_amount' => null,
        ]);

        $faelligVorher = $loan->repaymentPlanItems()
            ->where('status', RepaymentItemStatus::Planned->value)
            ->whereDate('due_date', '<=', '2026-06-15')
            ->count();
        $this->assertGreaterThan(0, $faelligVorher, 'Der Testaufbau muss faellige Positionen enthalten.');

        $this->artisan('app:roll-forward-schedules')->assertSuccessful();

        $this->assertSame(0, $loan->repaymentPlanItems()
            ->where('status', RepaymentItemStatus::Planned->value)
            ->whereDate('due_date', '<=', '2026-06-15')
            ->count());

        $fortgeschrieben = $loan->repaymentPlanItems()
            ->where('status', RepaymentItemStatus::Assumed->value)
            ->get();
        $this->assertCount($faelligVorher, $fortgeschrieben);
        foreach ($fortgeschrieben as $position) {
            $this->assertSame(PaymentOrigin::Assumed, $position->origin,
                'Die Herkunft muss die Annahme ausweisen, sonst waere sie von einer '
                .'bestaetigten Zahlung nicht zu unterscheiden.');
        }
    }

    public function test_zukuenftige_positionen_bleiben_unberuehrt(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');
        $loan->repaymentPlanItems()->update(['status' => RepaymentItemStatus::Planned->value]);

        $this->artisan('app:roll-forward-schedules')->assertSuccessful();

        $this->assertGreaterThan(0, $loan->repaymentPlanItems()
            ->where('status', RepaymentItemStatus::Planned->value)
            ->whereDate('due_date', '>', '2026-06-15')
            ->count(), 'Was noch nicht faellig ist, darf nicht fortgeschrieben werden.');
    }

    public function test_erfasste_abweichungen_bleiben_unberuehrt(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $position = $loan->repaymentPlanItems()->orderBy('due_date')->firstOrFail();
        $position->update([
            'status' => RepaymentItemStatus::Missed->value,
            'actual_amount' => '0.00',
        ]);

        $this->artisan('app:roll-forward-schedules')->assertSuccessful();

        $this->assertSame(RepaymentItemStatus::Missed, $position->fresh()->status,
            'Eine erfasste Abweichung darf der Lauf nicht ueberschreiben.');
    }

    public function test_ein_zweiter_lauf_findet_nichts_mehr_vor(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');
        $loan->repaymentPlanItems()->update(['status' => RepaymentItemStatus::Planned->value]);

        $this->artisan('app:roll-forward-schedules')->assertSuccessful();
        $this->artisan('app:roll-forward-schedules')
            ->expectsOutputToContain('0 Position(en) in 0 Darlehen fortgeschrieben')
            ->assertSuccessful();
    }

    public function test_der_zeitplan_kennt_den_lauf(): void
    {
        // Absicherung gegen einen Rueckfall: der Befehl muss im Zeitplan
        // stehen, sonst stoesst ihn der Cronjob nie an.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:roll-forward-schedules')
            ->assertSuccessful();
    }
}
