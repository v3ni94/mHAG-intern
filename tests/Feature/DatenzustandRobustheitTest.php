<?php

namespace Tests\Feature;

use App\Enums\EntityScopeMode;
use App\Models\Entity;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ein einzelner Datensatz darf keine Seite und keinen Ablauf ausschalten.
 *
 * Sammlung aus der systematischen Suche vom 30.08.2026, ausgelöst durch den
 * Produktivausfall: Ein nicht entschlüsselbares Zwei-Faktor-Geheimnis hatte
 * die Anmeldung und über die Middleware jede weitere Seite mit einem
 * Serverfehler 500 beendet. Gesucht wurden weitere Stellen desselben Musters.
 *
 * Jeder Test hier stellt einen Datenzustand her, der im Betrieb entstehen
 * kann, und verlangt: eine verständliche Meldung oder ein leeres Feld, aber
 * kein Abbruch.
 */
class DatenzustandRobustheitTest extends TestCase
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

    #[Test]
    public function unbekannter_datenscope_modus_legt_die_anwendung_nicht_lahm(): void
    {
        /*
         * Der Enum-Cast auf entity_scope_mode wirft einen ValueError bei einem
         * Wert, den die Aufzählung nicht kennt. Der angemeldete Benutzer wird
         * in jeder Anfrage geladen, der Ausfall hätte also jede Seite
         * betroffen, genau wie beim Zwei-Faktor-Geheimnis.
         */
        $admin = $this->admin();
        DB::table('users')->where('id', $admin->id)->update(['entity_scope_mode' => 'unbekannter_modus']);

        $admin = User::find($admin->id);

        $this->assertSame(EntityScopeMode::Include, $admin->entityScopeMode(),
            'Ein unbekannter Wert muss zur engeren Auslegung führen, nicht zum Abbruch.');
        $this->assertFalse($admin->usesEntityExclusion());

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function pruefpfad_vertraegt_ungueltiges_utf8_und_bricht_den_vorgang_nicht_ab(): void
    {
        /*
         * old_values, new_values und context sind als JSON gecastet. Ein
         * einziges ungültiges Byte, etwa aus einer kopierten Bankauskunft,
         * führte zu einer JsonEncodingException. Da der Prüfpfad innerhalb der
         * Transaktion des Fachvorgangs geschrieben wird, hätte das den ganzen
         * Vorgang abgebrochen, zum Beispiel ein Zahlungsstorno.
         */
        $admin = $this->admin();
        $this->actingAs($admin);

        $kaputt = 'Grund: '.chr(0xFF).chr(0xFE).' Sonderzeichen';
        $this->assertFalse(mb_check_encoding($kaputt, 'UTF-8'));

        $eintrag = AuditService::log('test.robustheit', $admin, ['alt' => $kaputt], ['neu' => $kaputt], ['a' => ['b' => $kaputt]]);

        $this->assertDatabaseHas('audit_logs', ['id' => $eintrag->id, 'action' => 'test.robustheit']);
        $this->assertStringContainsString('Grund', (string) ($eintrag->fresh()->old_values['alt'] ?? ''),
            'Der lesbare Teil muss erhalten bleiben, der Eintrag darf nicht leer sein.');
    }

    #[Test]
    public function benachrichtigung_ohne_zeitstempel_legt_nicht_jede_seite_lahm(): void
    {
        // Das Partial der Glocke liegt im Layout jeder angemeldeten Seite.
        $admin = $this->admin();

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Hinweis',
            'notifiable_type' => $admin->getMorphClass(),
            'notifiable_id' => $admin->id,
            'data' => json_encode(['message' => 'Ohne Zeitstempel']),
            'read_at' => null,
            'created_at' => null,
            'updated_at' => null,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ohne Zeitstempel');
    }

    #[Test]
    public function unpruefbares_datum_im_filter_der_liquiditaetsplanung(): void
    {
        $this->actingAs($this->admin())
            ->get(route('liquidity.index', ['preset' => 'custom', 'from' => 'kein Datum', 'to' => '31.02.2026']))
            ->assertOk();
    }

    #[Test]
    public function unpruefbarer_stichtag_im_report_ertrag_und_rendite(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index', ['report' => 'yield', 'as_of' => 'kein Datum']))
            ->assertOk();
    }

    #[Test]
    public function unvollstaendiger_backup_status_legt_das_dashboard_nicht_lahm(): void
    {
        /*
         * Der Statuseintrag wird als Einstellung gepflegt. Fehlt der Schlüssel
         * "success", etwa nach einem abgebrochenen Lauf oder einem Wechsel des
         * Formats, hätte das Dashboard mit einem Serverfehler geendet, also die
         * Seite direkt nach der Anmeldung.
         */
        Setting::set('backup', 'last_run', ['finished_at' => '30.08.2026 03:00']);

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Letztes Backup');
    }

    #[Test]
    public function personenakte_ohne_personensatz_meldet_statt_abzubrechen(): void
    {
        // Datenzustand: Entität als Person geführt, Personensatz fehlt.
        $entity = Entity::create(['type' => 'person', 'display_name' => 'Ohne Personensatz', 'status' => 'active']);

        $this->actingAs($this->admin())
            ->put(route('persons.update', $entity), [
                'first_name' => 'Timo',
                'last_name' => 'Müller',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function unternehmensakte_ohne_unternehmenssatz_meldet_statt_abzubrechen(): void
    {
        $entity = Entity::create(['type' => 'company', 'display_name' => 'Ohne Firmensatz', 'status' => 'active']);

        $this->actingAs($this->admin())
            ->put(route('companies.update', $entity), [
                'name' => 'Beispiel GmbH',
                'legal_form' => 'GmbH',
            ])
            ->assertNotFound();
    }
}
