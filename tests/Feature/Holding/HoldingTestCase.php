<?php

namespace Tests\Feature\Holding;

use App\Enums\EntityType;
use App\Enums\ShareTransactionStatus;
use App\Models\Entity;
use App\Models\Setting;
use App\Models\Shareholder;
use App\Models\ShareTransaction;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Basis für alle Holding-Tests: Seeder (Rollen + MHAG-Initialdaten inkl.
 * Timo Müller mit 100.000 Aktien über AB-INITIAL-0001) und Fake-Dokumentenablage.
 */
abstract class HoldingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(InitialDataSeeder::class);
    }

    /** Administrator (Timo Müller) mit erfüllter 2FA-Pflicht. */
    protected function admin(): User
    {
        $admin = User::query()->where('email', 'timo@muellerhv.de')->firstOrFail();
        $admin->forceFill([
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $admin->fresh();
    }

    /** Benutzer mit Leserechten (shares.view), aber ohne shares.finalize. */
    protected function readOnlyUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Nur Lesen');

        return $user;
    }

    protected function timo(): Shareholder
    {
        return Shareholder::query()
            ->whereHas('entity', fn ($q) => $q->where('internal_number', 'ENT-P-TMUELLER'))
            ->firstOrFail();
    }

    protected function mhagEntityId(): int
    {
        return (int) Setting::get('holding', 'company_entity_id');
    }

    /** Neuen Aktionär samt Entity anlegen. */
    protected function newShareholder(string $name = 'Neuaktionär GmbH', string $number = 'AKT-T001'): Shareholder
    {
        $entity = Entity::create([
            'type' => EntityType::Company,
            'display_name' => $name,
            'status' => 'active',
            'internal_number' => 'ENT-TEST-'.strtoupper(substr(md5($name.$number), 0, 8)),
        ]);

        return Shareholder::create([
            'entity_id' => $entity->id,
            'shareholder_number' => $number,
            'status' => 'active',
            'joined_on' => now()->toDateString(),
        ]);
    }

    /** Aktienbewegung mit sinnvollen Vorgaben anlegen (Status: Entwurf). */
    protected function makeTransaction(array $attributes = []): ShareTransaction
    {
        static $sequence = 0;
        $sequence++;

        return ShareTransaction::create(array_merge([
            'transaction_number' => 'AB-TEST-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT).'-'.uniqid(),
            'type' => 'sale',
            'share_count' => 1,
            'economic_transfer_date' => now()->toDateString(),
            'booking_date' => now()->toDateString(),
            'status' => ShareTransactionStatus::Draft->value,
        ], $attributes));
    }

    /** Minimaler, formal gültiger PDF-Inhalt für Upload-Tests. */
    protected function minimalPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n"
            ."xref\n0 4\ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n0\n%%EOF\n";
    }
}
