<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gespeichert wird in UTC, angezeigt und geplant in der Zeitzone des
 * Geschaeftsbetriebs. Ohne diese Trennung zeigte die Anwendung im Sommer
 * Uhrzeiten, die zwei Stunden zurueckliegen, und der Zeitplan lief zur
 * falschen Stunde.
 */
class BetriebszeitzoneTest extends TestCase
{
    public function test_die_anwendung_speichert_in_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function test_die_anzeigezeitzone_ist_europe_berlin(): void
    {
        $this->assertSame('Europe/Berlin', config('app.display_timezone'));
    }

    public function test_format_datetime_rechnet_von_utc_in_die_anzeigezeitzone_um(): void
    {
        // 25.09.2026 ist Sommerzeit, Europe/Berlin liegt zwei Stunden vor UTC.
        $utc = Carbon::parse('2026-09-25 22:15:00', 'UTC');

        $this->assertSame('26.09.2026 00:15', format_datetime($utc));
    }

    public function test_format_datetime_rechnet_auch_in_der_winterzeit_richtig(): void
    {
        // 15.01.2026 ist Normalzeit, Europe/Berlin liegt eine Stunde vor UTC.
        $utc = Carbon::parse('2026-01-15 23:30:00', 'UTC');

        $this->assertSame('16.01.2026 00:30', format_datetime($utc));
    }

    public function test_format_datetime_veraendert_den_uebergebenen_wert_nicht(): void
    {
        $utc = Carbon::parse('2026-09-25 22:15:00', 'UTC');

        format_datetime($utc);

        $this->assertSame('UTC', $utc->timezone->getName());
        $this->assertSame('2026-09-25 22:15:00', $utc->toDateTimeString());
    }

    public function test_format_date_bleibt_unveraendert(): void
    {
        $this->assertSame('25.09.2026', format_date('2026-09-25'));
    }

    public function test_die_anzeigezeitzone_faellt_auf_app_timezone_zurueck(): void
    {
        // Bestehende Installationen fuehren nur APP_TIMEZONE. Der Rueckfall
        // sorgt dafuer, dass sie nach der Lieferung sofort richtig rechnen.
        $inhalt = file_get_contents(config_path('app.php'));

        $this->assertStringContainsString(
            "env('APP_DISPLAY_TIMEZONE', env('APP_TIMEZONE', 'Europe/Berlin'))",
            $inhalt,
        );
    }

    public function test_die_geplanten_aufgaben_tragen_die_anzeigezeitzone(): void
    {
        $plan = app(\Illuminate\Console\Scheduling\Schedule::class);
        $befehle = [];

        foreach ($plan->events() as $ereignis) {
            if (str_contains((string) $ereignis->command, 'app:backup-run')) {
                $befehle['app:backup-run'] = $ereignis;
            }
            if (str_contains((string) $ereignis->command, 'app:scan-due-items')) {
                $befehle['app:scan-due-items'] = $ereignis;
            }
            if (str_contains((string) $ereignis->command, 'app:roll-forward-schedules')) {
                $befehle['app:roll-forward-schedules'] = $ereignis;
            }
        }

        $this->assertCount(3, $befehle, 'Es fehlen geplante Aufgaben.');

        foreach ($befehle as $name => $ereignis) {
            $this->assertSame(
                'Europe/Berlin',
                (string) $ereignis->timezone,
                "Die Aufgabe {$name} laeuft nicht in der Zeitzone des Geschaeftsbetriebs.",
            );
        }
    }

    public function test_die_uhrzeiten_der_aufgaben_sind_unveraendert(): void
    {
        $plan = app(\Illuminate\Console\Scheduling\Schedule::class);
        $erwartet = [
            'app:backup-run' => '0 2 * * *',
            'app:roll-forward-schedules' => '30 4 * * *',
            'app:scan-due-items' => '30 5 * * *',
        ];

        foreach ($plan->events() as $ereignis) {
            foreach ($erwartet as $name => $ausdruck) {
                if (str_contains((string) $ereignis->command, $name)) {
                    $this->assertSame($ausdruck, $ereignis->expression, "Uhrzeit von {$name}");
                }
            }
        }
    }
}
