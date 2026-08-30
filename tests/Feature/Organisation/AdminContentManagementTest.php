<?php

namespace Tests\Feature\Organisation;

use App\Models\ChangelogEntry;
use App\Models\DailyFact;

/**
 * Pflegeoberflächen für Changelog (Abschnitt 118) und die Tagesereignisse der
 * Fußzeile (Abschnitt 119). Es werden keine Beispieldaten erzeugt: ohne
 * gepflegten Eintrag bleibt die Anzeige leer.
 */
class AdminContentManagementTest extends OrganisationTestCase
{
    // ------------------------------------------------------------------
    // Changelog
    // ------------------------------------------------------------------

    public function test_changelog_eintrag_anlegen_bearbeiten_und_loeschen(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.changelog.index'))
            ->assertOk()
            ->assertSee('Noch keine Changelog-Einträge vorhanden.');

        $this->actingAs($admin)->get(route('admin.changelog.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.changelog.store'), [
            'version' => '1.1.0',
            'released_on' => '2026-08-30',
            'changes' => "## Neue Funktionen\n- Erste-Schritte-Assistent\n\n## Fehlerbehebungen\n- Datenschutzmodus greift in allen Geldanzeigen",
        ])->assertRedirect(route('admin.changelog.index'));

        $entry = ChangelogEntry::query()->firstOrFail();
        $this->assertSame('1.1.0', $entry->version);
        $this->assertSame('30.08.2026', format_date($entry->released_on));

        // Der Eintrag erscheint in der Ansicht "Was ist neu?".
        $this->actingAs($admin)->get(route('help.changelog'))
            ->assertOk()
            ->assertSee('Version 1.1.0')
            ->assertSee('Erste-Schritte-Assistent')
            ->assertSee('Fehlerbehebungen');

        $this->actingAs($admin)->put(route('admin.changelog.update', $entry), [
            'version' => '1.1.1',
            'released_on' => '2026-09-01',
            'changes' => "## Fehlerbehebungen\n- Hilfe-Suche findet einzelne Suchbegriffe",
        ])->assertRedirect(route('admin.changelog.index'));

        $this->assertSame('1.1.1', $entry->fresh()->version);

        $this->actingAs($admin)->delete(route('admin.changelog.destroy', $entry))
            ->assertRedirect(route('admin.changelog.index'));

        $this->assertSame(0, ChangelogEntry::query()->count());
    }

    public function test_changelog_pflichtfelder_werden_geprueft(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.changelog.create'))
            ->post(route('admin.changelog.store'), ['version' => '', 'released_on' => '', 'changes' => ''])
            ->assertSessionHasErrors(['version', 'released_on', 'changes']);

        $this->assertSame(0, ChangelogEntry::query()->count());
    }

    public function test_changelog_verwaltung_ist_fuer_externe_gesperrt(): void
    {
        $external = $this->makeUserWithRole('Darlehensgeber');

        $this->actingAs($external)->get(route('admin.changelog.index'))->assertForbidden();
        $this->actingAs($external)->post(route('admin.changelog.store'), [])->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Tagesereignisse der Fußzeile
    // ------------------------------------------------------------------

    public function test_ohne_eintrag_zeigt_der_footer_nichts(): void
    {
        $admin = $this->makeAdmin();

        $this->assertSame(0, DailyFact::query()->count(), 'Es dürfen keine Beispieleinträge vorbefüllt sein.');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Heute:');
    }

    public function test_wiederkehrenden_eintrag_anlegen_und_im_footer_sehen(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.daily-facts.index'))
            ->assertOk()
            ->assertSee('Noch keine Einträge vorhanden.');

        $this->actingAs($admin)->get(route('admin.daily-facts.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.daily-facts.store'), [
            'title' => 'Prüftag Datenschutzmodus',
            'description' => 'Beschreibung aus der belegten Quelle.',
            'source' => 'Interne Betriebsanweisung der Müller Holding AG',
            'country' => 'Deutschland',
            'recurring' => '1',
            'month_day' => now()->format('m-d'),
            'is_active' => '1',
        ])->assertRedirect(route('admin.daily-facts.index'));

        $fact = DailyFact::query()->firstOrFail();
        $this->assertTrue($fact->recurring);
        $this->assertNull($fact->specific_date);
        $this->assertSame(now()->format('m-d'), $fact->month_day);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Heute: Prüftag Datenschutzmodus');
    }

    public function test_einmaligen_eintrag_anlegen_leitet_monat_und_tag_ab(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.daily-facts.store'), [
            'title' => 'Hauptversammlung',
            'source' => 'Einladung vom 01.06.2026',
            'recurring' => '0',
            'specific_date' => '2026-06-15',
            'is_active' => '1',
        ])->assertRedirect(route('admin.daily-facts.index'));

        $fact = DailyFact::query()->firstOrFail();
        $this->assertFalse($fact->recurring);
        $this->assertSame('06-15', $fact->month_day);
        $this->assertSame('15.06.2026', format_date($fact->specific_date));
    }

    public function test_quelle_ist_pflichtfeld(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.daily-facts.create'))
            ->post(route('admin.daily-facts.store'), [
                'title' => 'Ohne Quelle',
                'recurring' => '1',
                'month_day' => '08-30',
            ])
            ->assertSessionHasErrors('source');

        $this->assertSame(0, DailyFact::query()->count());
    }

    public function test_unzulaessiges_datum_wird_abgelehnt(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.daily-facts.create'))
            ->post(route('admin.daily-facts.store'), [
                'title' => 'Falscher Tag',
                'source' => 'Quelle',
                'recurring' => '1',
                'month_day' => '02-30',
            ])
            ->assertSessionHasErrors('month_day');

        $this->assertSame(0, DailyFact::query()->count());
    }

    public function test_eintrag_bearbeiten_und_loeschen(): void
    {
        $admin = $this->makeAdmin();
        $fact = DailyFact::create([
            'month_day' => '01-15',
            'title' => 'Alter Titel',
            'source' => 'Quelle A',
            'recurring' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.daily-facts.edit', $fact))->assertOk();

        $this->actingAs($admin)->put(route('admin.daily-facts.update', $fact), [
            'title' => 'Neuer Titel',
            'source' => 'Quelle B',
            'recurring' => '1',
            'month_day' => '01-16',
            'is_active' => '0',
        ])->assertRedirect(route('admin.daily-facts.index'));

        $fact->refresh();
        $this->assertSame('Neuer Titel', $fact->title);
        $this->assertSame('01-16', $fact->month_day);
        $this->assertFalse($fact->is_active);

        $this->actingAs($admin)->delete(route('admin.daily-facts.destroy', $fact))
            ->assertRedirect(route('admin.daily-facts.index'));

        $this->assertSame(0, DailyFact::query()->count());
    }

    public function test_wussten_sie_verwaltung_ist_fuer_externe_gesperrt(): void
    {
        $external = $this->makeUserWithRole('Darlehensgeber');

        $this->actingAs($external)->get(route('admin.daily-facts.index'))->assertForbidden();
        $this->actingAs($external)->post(route('admin.daily-facts.store'), [])->assertForbidden();
    }
}
