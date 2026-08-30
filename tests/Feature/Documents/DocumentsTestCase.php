<?php

namespace Tests\Feature\Documents;

use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class DocumentsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');

        // 2FA-Pflicht für Tests deaktivieren (eigene 2FA-Tests liegen bei der Foundation).
        Setting::set('security', 'two_factor_required_roles', []);
    }

    protected function internalUser(string $role = 'Administrator'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /** Externer Benutzer (Darlehensgeber) mit eigener Entity. */
    protected function externalLender(): array
    {
        $entity = Entity::create([
            'type' => EntityType::Person,
            'display_name' => 'Externer Darlehensgeber',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['entity_id' => $entity->id, 'is_active' => true]);
        $user->assignRole('Darlehensgeber');

        return [$user, $entity];
    }

    protected function makeEntity(string $name, bool $withAddress = true): Entity
    {
        $entity = Entity::create([
            'type' => EntityType::Person,
            'display_name' => $name,
            'status' => 'active',
        ]);

        if ($withAddress) {
            $entity->addresses()->create([
                'type' => 'main',
                'street' => 'Rheinpromenade',
                'house_number' => '13',
                'postal_code' => '40789',
                'city' => 'Monheim am Rhein',
                'country' => 'Deutschland',
                'is_primary' => true,
            ]);
        }

        return $entity;
    }

    protected function makeLoan(array $attributes = []): Loan
    {
        $lender = $attributes['lender'] ?? $this->makeEntity('Geber GmbH');
        $borrower = $attributes['borrower'] ?? $this->makeEntity('Nehmer AG');
        unset($attributes['lender'], $attributes['borrower']);

        return Loan::create(array_merge([
            'loan_number' => 'DAR-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'title' => 'Testdarlehen',
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => '2026-01-01',
            'contract_date' => '2025-12-15',
            'disbursement_date' => '2026-01-05',
            'due_date' => '2028-12-31',
            'contract_end' => '2028-12-31',
            'principal_amount' => '50000.00',
        ], $attributes));
    }

    protected function fakePdf(string $name = 'unterlage.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%Testinhalt Müller Holding AG\n%%EOF");
    }
}
