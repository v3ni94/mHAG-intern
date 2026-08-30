<?php

namespace Tests\Feature;

use App\Models\DailyFact;
use App\Models\Setting;
use App\Models\User;
use App\Services\DailyEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tagesereignisse in der Fußzeile (Abschnitt 119, erweitert am 30.08.2026).
 *
 * Je Kalendertag ein Aktionstag, zum Beispiel der Welthundetag. Angezeigt wird
 * nur, was gepflegt und aktiv ist; für Tage ohne Eintrag bleibt die Fußzeile
 * leer, es wird nichts erfunden.
 */
class DailyEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00'));
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrator');

        return $user;
    }

    private function eintrag(array $overrides = []): DailyFact
    {
        return DailyFact::create(array_merge([
            'month_day' => '10-10',
            'title' => 'Welthundetag',
            'description' => 'Aktionstag zum Schutz und zur Vermittlung von Hunden.',
            'source' => 'International Fund for Animal Welfare',
            'recurring' => true,
            'is_active' => true,
        ], $overrides));
    }

    public function test_fusszeile_zeigt_das_ereignis_des_tages(): void
    {
        $this->eintrag();

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Heute: Welthundetag');
        $response->assertDontSee('Wussten Sie');
    }

    public function test_fusszeile_bleibt_ohne_eintrag_leer(): void
    {
        $this->eintrag(['month_day' => '12-24']);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Heute:');
    }

    public function test_inaktiver_eintrag_wird_nicht_angezeigt(): void
    {
        $this->eintrag(['is_active' => false]);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Welthundetag');
    }

    public function test_mehrere_ereignisse_je_tag_werden_benannt(): void
    {
        $this->eintrag();
        $this->eintrag(['title' => 'Welttag der seelischen Gesundheit', 'source' => 'Weltgesundheitsorganisation']);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Heute: Welthundetag');
        $response->assertSee('und 1 weiterer');
    }

    public function test_einmaliger_eintrag_hat_vorrang(): void
    {
        // Ein Eintrag für ein konkretes Datum ist die genauere Angabe.
        $this->eintrag();
        $this->eintrag([
            'title' => 'Hauptversammlung der Müller Holding AG',
            'source' => 'Einladung vom 01.09.2026',
            'recurring' => false,
            'specific_date' => '2026-10-10',
        ]);

        $ergebnis = app(DailyEventService::class)->forDate();

        $this->assertSame('Hauptversammlung der Müller Holding AG', $ergebnis['event']->title);
        $this->assertSame(1, $ergebnis['others']->count());
    }

    public function test_auswahl_ist_innerhalb_eines_tages_stabil(): void
    {
        $this->eintrag();
        $this->eintrag(['title' => 'Zweiter Tag', 'source' => 'Quelle']);
        $service = app(DailyEventService::class);

        $erste = $service->forDate()['event']->id;
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame($erste, $service->forDate()['event']->id);
        }
    }

    public function test_abdeckung_weist_luecken_aus(): void
    {
        $this->eintrag();
        $this->eintrag(['month_day' => '01-01', 'title' => 'Neujahr', 'source' => 'Feiertagsgesetze der Länder']);
        // Inaktive Einträge zählen nicht als belegter Tag
        $this->eintrag(['month_day' => '02-02', 'title' => 'Inaktiv', 'source' => 'Quelle', 'is_active' => false]);

        $abdeckung = app(DailyEventService::class)->coverage();

        $this->assertSame(366, $abdeckung['total']);
        $this->assertSame(2, $abdeckung['covered']);
        $this->assertSame(2, $abdeckung['entries']);
        $this->assertContains('02-02', $abdeckung['gaps']['02']);
        $this->assertNotContains('01-01', $abdeckung['gaps']['01'] ?? []);
        // Der 29. Februar bleibt pflegbar
        $this->assertContains('02-29', $abdeckung['gaps']['02']);
    }

    public function test_verwaltung_zeigt_abdeckung_und_offene_tage(): void
    {
        $this->eintrag();

        $response = $this->actingAs($this->admin())->get(route('admin.daily-facts.index'));

        $response->assertOk();
        $response->assertSee('Tagesereignisse der Fußzeile');
        $response->assertSee('Belegte Kalendertage');
        $response->assertSee('1 von 366');
        $response->assertSee('Offene Tage je Monat');
        $response->assertSee('Heute in der Fußzeile');
        $response->assertSee('Welthundetag');
    }

    public function test_quelle_bleibt_pflichtangabe(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.daily-facts.store'), [
            'title' => 'Tag ohne Beleg',
            'month_day' => '05-05',
            'recurring' => '1',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('source');
        $this->assertDatabaseMissing('daily_facts', ['title' => 'Tag ohne Beleg']);
    }

    public function test_29_februar_ist_zulaessig(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.daily-facts.store'), [
            'title' => 'Tag der Schaltsekunde',
            'source' => 'Interne Pflege',
            'month_day' => '02-29',
            'recurring' => '1',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('daily_facts', ['month_day' => '02-29']);
    }

    public function test_unmoeglicher_tag_wird_abgewiesen(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.daily-facts.store'), [
            'title' => 'Tag, den es nicht gibt',
            'source' => 'Interne Pflege',
            'month_day' => '02-30',
            'recurring' => '1',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('month_day');
    }
}
