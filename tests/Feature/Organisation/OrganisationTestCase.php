<?php

namespace Tests\Feature\Organisation;

use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gemeinsame Basis der Organisations-/Admin-Tests (Agent F).
 */
abstract class OrganisationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // 2FA-Pflicht ist einstellungsgesteuert; für die Feature-Tests deaktiviert,
        // damit Seitenaufrufe nicht zur 2FA-Einrichtung umgeleitet werden.
        \App\Models\Setting::set('security', 'two_factor_required_roles', []);
    }

    protected function makeAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrator');

        return $user;
    }

    protected function makeUserWithRole(string $role, ?int $entityId = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'entity_id' => $entityId]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeEntity(string $name, string $type = 'person'): Entity
    {
        return Entity::create([
            'type' => $type === 'person' ? EntityType::Person : EntityType::Company,
            'display_name' => $name,
            'status' => 'active',
        ]);
    }

    protected function makeLoan(Entity $lender, Entity $borrower, array $attributes = []): Loan
    {
        static $sequence = 0;
        $sequence++;

        return Loan::create(array_merge([
            'loan_number' => sprintf('DAR-2026-%05d', $sequence + 900),
            'title' => 'Testdarlehen '.$sequence,
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => today()->subYear()->toDateString(),
            'principal_amount' => '100000.00',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Ausgeglichene Bilanz-Antwort für gemockte LoanBalanceService-Aufrufe.
     */
    protected function zeroBalances(): array
    {
        return [
            'disbursed' => '0.00', 'repaid' => '0.00', 'principal_outstanding' => '0.00',
            'interest_charged' => '0.00', 'interest_confirmed' => '0.00', 'interest_assumed' => '0.00',
            'interest_open' => '0.00', 'fees_charged' => '0.00', 'fees_paid' => '0.00', 'fees_open' => '0.00',
            'default_interest' => '0.00', 'payments_received' => '0.00', 'total_receivable' => '0.00',
            'overdue_amount' => '0.00', 'next_due_date' => null, 'next_due_amount' => '0.00',
        ];
    }
}
