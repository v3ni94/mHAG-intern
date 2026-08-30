<?php

namespace Tests\Feature\MasterData;

use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserEntityAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Basis der Stammdaten-Tests: Rollen/Permissions-Seeding, Benutzer-Helfer
 * und Fallback-Routen für parallel entstehende Module (Layout und Views
 * verweisen auf Routennamen anderer Agenten; fehlen diese noch, werden
 * hier Test-Stubs registriert, damit die Views renderbar bleiben).
 */
abstract class MasterDataTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // 2FA-Pflicht im Test deaktivieren, damit Rollen wie Administrator
        // nicht auf die Einrichtungsseite umgeleitet werden.
        Setting::set('security', 'two_factor_required_roles', []);

        $this->registerFallbackRoutes();
    }

    /** Routennamen fremder Module, die Layout/Views referenzieren. */
    private function registerFallbackRoutes(): void
    {
        $names = [
            'dashboard', 'calendar.index', 'reminders.index', 'help.index',
            'notifications.index', 'notifications.read-all',
            'loans.index', 'loans.show', 'payments.index', 'due-items.index',
            'securities.index', 'liquidity.index',
            'contracts.index', 'contracts.show', 'documents.index', 'documents.show', 'documents.download',
            'holding.dashboard', 'shareholders.index', 'share-transactions.index', 'share-transactions.show',
            'investments.index', 'corporate-bodies.index', 'resolutions.index', 'resolutions.show',
            'signatures.index', 'reports.index',
            'admin.users.index', 'admin.roles.index', 'admin.settings.index', 'admin.sftp.index',
            'admin.backups.index', 'admin.audit.index', 'admin.status',
        ];

        $added = false;
        foreach ($names as $name) {
            if (! Route::has($name)) {
                Route::get('/_test-stub/'.str_replace('.', '-', $name).'/{p1?}/{p2?}', fn () => 'stub')->name($name);
                $added = true;
            }
        }

        if ($added) {
            Route::getRoutes()->refreshNameLookups();
        }
    }

    protected function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrator');

        return $user;
    }

    protected function internalUser(string $role = 'Sachbearbeiter'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Externer Benutzer (z. B. Darlehensgeber) mit eigener Entity-Zuordnung.
     * Zusätzliche Einzel-Permissions optional (z. B. persons.view).
     */
    protected function externalUser(Entity $entity, string $role = 'Darlehensgeber', array $extraPermissions = []): User
    {
        $user = User::factory()->create(['is_active' => true, 'entity_id' => $entity->id]);
        $user->assignRole($role);
        foreach ($extraPermissions as $permission) {
            $user->givePermissionTo($permission);
        }

        UserEntityAssignment::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'context' => 'self',
            'label' => 'Privat',
            'is_default' => true,
        ]);

        return $user;
    }

    protected function createPersonEntity(string $first = 'Max', string $last = 'Mustermann', array $entityAttributes = []): Entity
    {
        $entity = Entity::create(array_merge([
            'type' => EntityType::Person,
            'display_name' => $first.' '.$last,
            'status' => 'active',
        ], $entityAttributes));

        $entity->person()->create(['first_name' => $first, 'last_name' => $last]);

        return $entity->fresh(['person']);
    }

    protected function createCompanyEntity(string $name = 'Beispiel GmbH', array $entityAttributes = []): Entity
    {
        $entity = Entity::create(array_merge([
            'type' => EntityType::Company,
            'display_name' => $name,
            'status' => 'active',
        ], $entityAttributes));

        $entity->company()->create(['name' => $name, 'legal_form' => 'GmbH']);

        return $entity->fresh(['company']);
    }
}
