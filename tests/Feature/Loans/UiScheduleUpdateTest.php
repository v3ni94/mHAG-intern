<?php

namespace Tests\Feature\Loans;

use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Models\RepaymentPlanItem;

/**
 * Soll/Ist-Monatserfassung (Abschnitte 26-28 Masterprompt).
 */
class UiScheduleUpdateTest extends LoansUiTestCase
{
    private function makeInterestItem(array $attributes = []): RepaymentPlanItem
    {
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        return $loan->repaymentPlanItems()->create(array_merge([
            'item_type' => 'interest',
            'due_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'planned_amount' => '500.00',
            'status' => 'assumed',
            'origin' => 'assumed',
        ], $attributes));
    }

    public function test_ist_erfassung_setzt_betrag_status_und_herkunft_und_rechnet_neu(): void
    {
        $mocks = $this->mockLoanServices();
        $item = $this->makeInterestItem();
        $mocks['recalculation']->shouldReceive('recalculate')
            ->once()
            ->withArgs(fn ($loan, $trigger) => $loan->id === $item->loan_id && $trigger === 'schedule.actual_recorded')
            ->andReturn(new \App\Models\LoanRecalculation);

        $user = $this->makeInternalUser('Buchhaltung');

        $response = $this->actingAs($user)->put(route('loans.schedule.update', $item), [
            'status' => 'partial',
            'actual_amount' => '300,00',
            'actual_date' => now()->subMonths(2)->startOfMonth()->addDays(3)->toDateString(),
            'comment' => 'Teilzahlung eingegangen',
        ]);

        $response->assertRedirect();

        $item->refresh();
        $this->assertSame('300.00', (string) $item->actual_amount);
        $this->assertSame(RepaymentItemStatus::Partial, $item->status);
        $this->assertSame(PaymentOrigin::ManualConfirmed, $item->origin);
        $this->assertSame('Teilzahlung eingegangen', $item->comment);
        $this->assertFalse($item->manually_adjusted, 'manually_adjusted darf nur bei SOLL-Änderung gesetzt werden.');
        $this->assertSame('200.00', $item->openAmount());

        $this->assertDatabaseHas('audit_logs', ['action' => 'loans.schedule_item_updated']);
    }

    public function test_nicht_bezahlt_setzt_ist_auf_null_betrag(): void
    {
        $this->mockLoanServices();
        $item = $this->makeInterestItem();
        $user = $this->makeInternalUser('Buchhaltung');

        $this->actingAs($user)->put(route('loans.schedule.update', $item), [
            'status' => 'missed',
            'comment' => 'Zahlung ist ausgefallen',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('0.00', (string) $item->actual_amount);
        $this->assertSame(RepaymentItemStatus::Missed, $item->status);
        $this->assertSame('500.00', $item->openAmount());
    }

    public function test_soll_aenderung_setzt_manually_adjusted(): void
    {
        $this->mockLoanServices();
        $item = $this->makeInterestItem();
        $user = $this->makeInternalUser('Buchhaltung');

        $this->actingAs($user)->put(route('loans.schedule.update', $item), [
            'status' => 'confirmed',
            'actual_amount' => '600,00',
            'planned_amount' => '600,00',
        ])->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->manually_adjusted);
        $this->assertSame('600.00', (string) $item->planned_amount);
        $this->assertSame(RepaymentItemStatus::Confirmed, $item->status);
    }

    public function test_position_eines_fremden_darlehens_ist_fuer_externe_unerreichbar(): void
    {
        $this->mockLoanServices();
        $item = $this->makeInterestItem();
        $andereFirma = $this->makeEntity('Unbeteiligte GmbH');
        // Darlehensnehmer hat keine payments.record-Berechtigung: 403;
        // entscheidend ist, dass der Datensatz nicht verändert wird.
        $extern = $this->makeExternalUser('Darlehensnehmer', $andereFirma);

        $this->actingAs($extern)->put(route('loans.schedule.update', $item), [
            'status' => 'confirmed',
            'actual_amount' => '500,00',
        ])->assertForbidden();

        $this->assertSame(RepaymentItemStatus::Assumed, $item->fresh()->status);
    }

    public function test_ist_betrag_ist_pflicht_ausser_bei_nicht_bezahlt(): void
    {
        $this->mockLoanServices();
        $item = $this->makeInterestItem();
        $user = $this->makeInternalUser('Buchhaltung');

        $response = $this->actingAs($user)->from(route('loans.show', [$item->loan_id, 'tab' => 'soll-ist']))
            ->put(route('loans.schedule.update', $item), [
                'status' => 'confirmed',
            ]);

        $response->assertSessionHasErrors('actual_amount');
        $this->assertSame(RepaymentItemStatus::Assumed, $item->fresh()->status);
    }
}
