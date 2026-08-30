<?php

namespace Tests\Feature\Loans;

use App\Enums\EntityType;
use App\Enums\LoanStatus;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Basis für die UI-Tests des Darlehensmoduls (Agent C).
 *
 * Die Loans-Services (Agent B) entstehen parallel: Existieren die Klassen
 * noch nicht, werden minimale Fakes definiert, die anschließend über
 * $this->mock(...) durch Mocks ersetzt werden (Bauplan Teil 2).
 */
abstract class LoansUiTestCase extends TestCase
{
    use RefreshDatabase;

    protected static int $loanCounter = 0;

    protected function setUp(): void
    {
        static::ensureLoanServiceClassesExist();

        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // 2FA-Pflicht für Tests deaktivieren (eigene 2FA-Tests liegen bei der Foundation)
        Setting::set('security', 'two_factor_required_roles', []);

        // Dokumentenablage im Test nur im Speicher (Snapshots der
        // Forderungsaufstellung, Abschnitt 39)
        \Illuminate\Support\Facades\Storage::fake(config('documents.disk'));

        $this->registerLayoutRouteFallbacks();
    }

    /**
     * Fehlen Modul-Routen anderer Agenten (Layout-Sidebar), werden neutrale
     * Platzhalter-Routen registriert, damit route() im Layout auflösbar ist.
     */
    protected function registerLayoutRouteFallbacks(): void
    {
        $names = [
            'dashboard', 'calendar.index', 'reminders.index', 'reports.index',
            'help.index', 'search.index', 'notifications.index', 'notifications.read-all',
            'persons.index', 'companies.index', 'contracts.index', 'contracts.create',
            'contracts.show', 'documents.index', 'documents.create', 'documents.show',
            'documents.download', 'holding.dashboard', 'shareholders.index',
            'share-transactions.index', 'investments.index', 'corporate-bodies.index',
            'resolutions.index', 'signatures.index',
            'admin.users.index', 'admin.roles.index', 'admin.settings.index',
            'admin.sftp.index', 'admin.backups.index', 'admin.audit.index', 'admin.status',
        ];
        foreach ($names as $index => $name) {
            if (! Route::has($name)) {
                Route::get('/__test-stub/'.$index, fn () => '')->name($name);
            }
        }
    }

    /**
     * Minimale Fakes für noch nicht vorhandene Loans-Services (Agent B),
     * damit Constructor-/Method-Injection und $this->mock(...) funktionieren.
     */
    protected static function ensureLoanServiceClassesExist(): void
    {
        $definitions = [
            'InterestCalculationService' => '
                public function dayCountFactor($method, $from, $to): string { return "0.0000000000"; }
                public function interestForPeriod($principal, $ratePercent, $method, $from, $to): string { return "0.0000000000"; }
                public function interestForLoanPeriod($loan, $from, $to): string { return "0.00"; }',
            'LoanScheduleService' => '
                public function generate($loan): void {}
                public function rollForwardAssumed($loan, $asOf = null): void {}',
            'LoanBalanceService' => '
                public function balances($loan, $asOf = null): array { return []; }
                public function statementRows($loan, $asOf): array { return []; }',
            'LoanRecalculationService' => '
                public function recalculate($loan, $trigger, $earliestAffectedDate = null, $user = null) { return new \App\Models\LoanRecalculation; }',
            'PaymentAllocationService' => '
                public function allocate($payment, $manualBuckets = null): array { return []; }',
            'DisbursementService' => '
                public function plan($loan, array $data, $user = null) { return new \App\Models\LoanDisbursement; }
                public function planMany($loan, array $rows, $user = null): array { return []; }
                public function confirm($disbursement, $actualAmount, $actualDate, $origin, $user = null): void {}
                public function markFailed($disbursement, $note = null, $user = null): void {}
                public function cancel($disbursement, $reason = null, $user = null): void {}',
        ];

        foreach ($definitions as $class => $body) {
            if (! class_exists('App\\Services\\Loans\\'.$class)) {
                eval('namespace App\\Services\\Loans; class '.$class.' { '.$body.' }');
            }
        }
    }

    /** Alle Loans-Services als Mocks binden; Rückgabe zum Nachschärfen von Erwartungen. */
    protected function mockLoanServices(?array $balances = null): array
    {
        $balances ??= $this->balanceStub();

        $mocks = [];
        $mocks['schedule'] = $this->mock(\App\Services\Loans\LoanScheduleService::class, function ($mock) {
            $mock->shouldReceive('generate')->byDefault();
            $mock->shouldReceive('rollForwardAssumed')->byDefault();
        });
        $mocks['balance'] = $this->mock(\App\Services\Loans\LoanBalanceService::class, function ($mock) use ($balances) {
            $mock->shouldReceive('balances')->andReturn($balances)->byDefault();
            $mock->shouldReceive('statementRows')->andReturn(['rows' => [
                ['label' => 'Kapital', 'amount' => '100000.00', 'sign' => '+'],
            ], 'total' => '100000.00'])->byDefault();
            $mock->shouldReceive('accountBalance')->andReturn('100000.00')->byDefault();
            $mock->shouldReceive('accountBalancesFor')->andReturn([])->byDefault();
        });
        $mocks['recalculation'] = $this->mock(\App\Services\Loans\LoanRecalculationService::class, function ($mock) {
            $mock->shouldReceive('recalculate')->andReturn(new \App\Models\LoanRecalculation)->byDefault();
        });
        $mocks['allocation'] = $this->mock(\App\Services\Loans\PaymentAllocationService::class, function ($mock) {
            $mock->shouldReceive('allocate')->andReturn(['interest' => '0.00'])->byDefault();
        });
        $mocks['disbursement'] = $this->mock(\App\Services\Loans\DisbursementService::class, function ($mock) {
            $mock->shouldReceive('plan')->andReturn(new \App\Models\LoanDisbursement)->byDefault();
            $mock->shouldReceive('planMany')->andReturn([])->byDefault();
            $mock->shouldReceive('confirm')->byDefault();
            $mock->shouldReceive('markFailed')->byDefault();
            $mock->shouldReceive('cancel')->byDefault();
        });

        return $mocks;
    }

    /** Plausibler Rückgabewert für LoanBalanceService::balances (Bauplan-Keys). */
    protected function balanceStub(): array
    {
        return [
            'disbursed' => '100000.00',
            'repaid' => '0.00',
            'principal_outstanding' => '100000.00',
            'interest_charged' => '3000.00',
            'interest_confirmed' => '2000.00',
            'interest_assumed' => '500.00',
            'interest_open' => '500.00',
            'interest_capitalized' => '0.00',
            'capitalized' => '0.00',
            'written_off' => '0.00',
            'fees_charged' => '0.00',
            'fees_paid' => '0.00',
            'fees_open' => '0.00',
            'default_interest' => '0.00',
            'account_balance' => '100000.00',
            'payments_received' => '2000.00',
            'total_receivable' => '100500.00',
            'overdue_amount' => '0.00',
            'next_due_date' => now()->addMonth()->toDateString(),
            'next_due_amount' => '500.00',
        ];
    }

    protected function makeInternalUser(string $role = 'Sachbearbeiter'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeExternalUser(string $role, Entity $entity): User
    {
        $user = User::factory()->create(['is_active' => true, 'entity_id' => $entity->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeEntity(string $name, EntityType $type = EntityType::Company): Entity
    {
        return Entity::create([
            'type' => $type,
            'display_name' => $name,
            'status' => 'active',
        ]);
    }

    protected function makeLoan(Entity $lender, Entity $borrower, array $attributes = []): Loan
    {
        static::$loanCounter++;

        return Loan::create(array_merge([
            'loan_number' => 'DAR-2026-'.str_pad((string) static::$loanCounter, 5, '0', STR_PAD_LEFT),
            'title' => 'Testdarlehen '.static::$loanCounter,
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => now()->subMonths(6)->toDateString(),
            'principal_amount' => '100000.00',
            'currency' => 'EUR',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'status' => LoanStatus::Active,
        ], $attributes));
    }
}
