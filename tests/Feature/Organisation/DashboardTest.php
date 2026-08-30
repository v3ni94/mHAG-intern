<?php

namespace Tests\Feature\Organisation;

use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Models\RepaymentPlanItem;
use App\Services\Loans\LoanBalanceService;

class DashboardTest extends OrganisationTestCase
{
    public function test_dashboard_rendert_fuer_administrator_mit_kpis_und_heute_relevant(): void
    {
        $lender = $this->makeEntity('Müller Holding AG', 'company');
        $borrower = $this->makeEntity('Testfirma GmbH', 'company');
        $loan = $this->makeLoan($lender, $borrower);

        // Überfällige (erfasste) Zinsrate -> "Heute relevant" in Rot
        RepaymentPlanItem::create([
            'loan_id' => $loan->id,
            'item_type' => RepaymentItemType::Interest->value,
            'due_date' => today()->subMonth()->toDateString(),
            'planned_amount' => '500.00',
            'actual_amount' => '0.00',
            'status' => RepaymentItemStatus::Missed->value,
            'origin' => 'manual_entered',
        ]);

        $this->mock(LoanBalanceService::class, function ($mock) {
            $mock->shouldReceive('balances')->andReturn(array_merge($this->zeroBalances(), [
                'disbursed' => '100000.00',
                'principal_outstanding' => '100000.00',
                'total_receivable' => '100500.00',
                'interest_open' => '500.00',
                'overdue_amount' => '500.00',
            ]));
        });

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Heute relevant');
        $response->assertSee('1 Zahlung überfällig');
        $response->assertSee('Gesamtportfolio');
        $response->assertSee('Überfälliges Kapital');
        // Admin-Zusatzblock (Abschnitt 136)
        $response->assertSee('Systemstatus (Administration)');
    }

    public function test_dashboard_rendert_fuer_externe_rolle_nur_eigene_zahlen(): void
    {
        $ownEntity = $this->makeEntity('Externer Darlehensgeber');
        $foreignLender = $this->makeEntity('Fremder Geber');
        $foreignBorrower = $this->makeEntity('Fremder Nehmer');

        // Fremdes Darlehen: darf für externen Benutzer nicht einfließen
        $this->makeLoan($foreignLender, $foreignBorrower);

        $balanceMock = $this->mock(LoanBalanceService::class);
        $balanceMock->shouldReceive('balances')->andReturn($this->zeroBalances());

        $external = $this->makeUserWithRole('Darlehensgeber', $ownEntity->id);

        $response = $this->actingAs($external)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Heute relevant');
        // Externe sehen den Admin-Block nicht
        $response->assertDontSee('Systemstatus (Administration)');
        // Kein sichtbares Darlehen -> keine Statuszahl aus fremdem Darlehen
        $response->assertSee('Aktive Darlehen');
        $this->assertStringNotContainsString('Fremder Geber', $response->getContent());
    }
}
