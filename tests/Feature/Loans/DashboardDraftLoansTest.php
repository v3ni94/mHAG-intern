<?php

namespace Tests\Feature\Loans;

use App\Enums\LoanStatus;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sichtbarkeit angelegter Darlehen im Dashboard.
 *
 * Anlass ist eine Rückmeldung aus dem Betrieb am 30.08.2026: Das Dashboard
 * zeigte in der Gesamtansicht angelegte Darlehen nicht an. Zu klären war, ob
 * der Datenscope greift oder der Statusfilter.
 */
class DashboardDraftLoansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrator');

        return $user->fresh();
    }

    private function entity(string $name): Entity
    {
        return Entity::create([
            'type' => 'company',
            'display_name' => $name,
            'status' => 'active',
        ]);
    }

    private function darlehen(LoanStatus $status): Loan
    {
        $loan = Loan::create([
            'loan_number' => 'DAR-2026-'.str_pad((string) ++self::$folge, 5, '0', STR_PAD_LEFT),
            'title' => 'Testdarlehen',
            'lender_entity_id' => $this->entity('Geber '.self::$folge)->id,
            'borrower_entity_id' => $this->entity('Nehmer '.self::$folge)->id,
            'effective_from' => '2026-01-01',
            'contract_end' => '2028-12-31',
            'principal_amount' => '100000.00',
            'currency' => 'EUR',
            'interest_method' => 'act_365',
            'interest_frequency' => 'annual',
            'repayment_model' => 'bullet',
            'status' => $status,
        ]);
        $loan->interestTerms()->create(['rate' => '5.000000', 'valid_from' => '2026-01-01']);

        return $loan;
    }

    private static int $folge = 0;

    #[Test]
    public function ein_aktives_darlehen_fremder_personen_erscheint_fuer_den_administrator(): void
    {
        // Gegenprobe zum Datenscope: ein Darlehen ohne jede Zuordnung zum
        // Administrator muss in der Gesamtansicht erscheinen.
        $this->darlehen(LoanStatus::Active);

        $kpis = app(DashboardService::class)->loanKpis($this->admin());

        $this->assertSame('1', $kpis['active_loans']['value'],
            'Interne Rollen sehen den Gesamtbestand. Erscheint das Darlehen nicht, liegt es am '
            .'Datenscope.');
    }

    #[Test]
    public function ein_darlehen_im_entwurf_wird_in_den_finanzkennzahlen_nicht_mitgezaehlt(): void
    {
        // Fachlich richtig: ein Entwurf ist keine Forderung. Er darf das
        // Gesamtportfolio nicht erhoehen.
        $this->darlehen(LoanStatus::Draft);

        $kpis = app(DashboardService::class)->loanKpis($this->admin());

        $this->assertSame('0.00', $kpis['total_portfolio']['value']);
        $this->assertSame('0', $kpis['active_loans']['value']);
    }

    #[Test]
    public function ein_darlehen_im_entwurf_wird_aber_ausgewiesen(): void
    {
        // Der eigentliche Mangel: der Entwurf war nirgends erkennbar. Wer ein
        // Darlehen anlegt und danach ein leeres Dashboard sieht, kann den
        // Zustand nicht einordnen.
        $this->darlehen(LoanStatus::Draft);
        $this->darlehen(LoanStatus::Draft);

        $kpis = app(DashboardService::class)->loanKpis($this->admin());

        $this->assertArrayHasKey('draft_loans', $kpis);
        $this->assertSame('2', $kpis['draft_loans']['value']);
        $this->assertFalse($kpis['draft_loans']['money']);
    }

    #[Test]
    public function das_dashboard_benennt_entwuerfe_sichtbar(): void
    {
        $this->darlehen(LoanStatus::Draft);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Darlehen im Entwurf');
    }

    #[Test]
    public function ohne_entwuerfe_bleibt_die_kennzahl_unauffaellig(): void
    {
        $this->darlehen(LoanStatus::Active);

        $kpis = app(DashboardService::class)->loanKpis($this->admin());

        $this->assertSame('0', $kpis['draft_loans']['value']);
        $this->assertNull($kpis['draft_loans']['severity']);
    }
}
