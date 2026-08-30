<?php

namespace Tests\Feature\Organisation;

use App\Models\Reminder;
use App\Services\Loans\LoanBalanceService;

/**
 * Render-Smoke-Tests: alle Seiten des Organisations- und Admin-Moduls
 * liefern HTTP 200 für den Administrator; Berechtigungen greifen für
 * Benutzer ohne Admin-Rechte.
 */
class PagesSmokeTest extends OrganisationTestCase
{
    public function test_alle_organisationsseiten_rendern(): void
    {
        $this->mock(LoanBalanceService::class, fn ($m) => $m->shouldReceive('balances')->andReturn($this->zeroBalances()));

        $admin = $this->makeAdmin();
        Reminder::create([
            'title' => 'Testwiedervorlage',
            'due_date' => today()->subDay()->toDateString(),
            'assigned_to' => $admin->id,
            'priority' => 'high',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $routes = [
            route('calendar.index'),
            route('calendar.index', ['month' => today()->format('Y-m'), 'day' => today()->toDateString()]),
            route('reminders.index'),
            route('reminders.create'),
            route('notifications.index'),
            route('reports.index'),
            route('help.index'),
            route('help.search', ['q' => 'Zinsen']),
            route('help.changelog'),
            route('faq.index'),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.invitations.index'),
            route('admin.roles.index'),
            route('admin.roles.create'),
            route('admin.settings.index'),
            route('admin.audit.index'),
            route('admin.backups.index'),
            route('admin.status'),
            route('admin.faq.index'),
            route('admin.faq.create'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // Alle Hilfeseiten der Whitelist rendern
        foreach (array_keys(\App\Http\Controllers\HelpController::PAGES) as $slug) {
            $this->actingAs($admin)->get(route('help.page', $slug))->assertOk();
        }

        // Alle Reports rendern (HTML)
        foreach (array_keys(\App\Http\Controllers\ReportController::REPORTS) as $key) {
            $this->actingAs($admin)->get(route('reports.show', $key))->assertOk();
        }
    }

    public function test_admin_bereiche_sind_fuer_externe_gesperrt(): void
    {
        $external = $this->makeUserWithRole('Darlehensgeber');

        $this->actingAs($external)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($external)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($external)->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($external)->get(route('admin.backups.index'))->assertForbidden();
        $this->actingAs($external)->get(route('admin.status'))->assertForbidden();

        // Externe ohne shares.view/resolutions.view: Reports mit Zusatzrechten gesperrt
        $this->actingAs($external)->get(route('reports.show', 'aktionaersliste'))->assertForbidden();
        $this->actingAs($external)->get(route('reports.show', 'beschlussregister'))->assertForbidden();
    }
}
