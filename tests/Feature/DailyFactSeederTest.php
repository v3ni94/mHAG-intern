<?php

namespace Tests\Feature;

use App\Models\DailyFact;
use App\Services\DailyEventService;
use Database\Seeders\DailyFactSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Tagesereignisse sind Stammdaten, keine Zierde. Geprüft wird deshalb,
 * dass der Seeder wiederholbar ist, dass jeder Eintrag eine Quelle trägt und
 * dass kein Eintrag auf einem Datum liegt, das es nicht gibt.
 */
class DailyFactSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_legt_die_ereignisse_an(): void
    {
        $this->seed(DailyFactSeeder::class);

        $this->assertGreaterThan(400, DailyFact::count(),
            'Die Sammlung sollte mehr als 400 belegte Aktionstage enthalten.');
    }

    #[Test]
    public function seeder_ist_wiederholbar_und_erzeugt_keine_doppelungen(): void
    {
        $this->seed(DailyFactSeeder::class);
        $ersteAnzahl = DailyFact::count();

        $this->seed(DailyFactSeeder::class);

        $this->assertSame($ersteAnzahl, DailyFact::count(),
            'Ein zweiter Durchlauf darf keine zusätzlichen Einträge erzeugen.');
    }

    #[Test]
    public function seeder_laesst_von_hand_gepflegte_eintraege_unberuehrt(): void
    {
        $eigener = DailyFact::create([
            'month_day' => '01-03',
            'title' => 'Gründungstag der Müller Holding AG',
            'description' => 'Interner Eintrag.',
            'source' => 'Eigene Erfassung',
            'recurring' => true,
            'is_active' => true,
        ]);

        $this->seed(DailyFactSeeder::class);

        $this->assertDatabaseHas('daily_facts', [
            'id' => $eigener->id,
            'title' => 'Gründungstag der Müller Holding AG',
            'source' => 'Eigene Erfassung',
        ]);
    }

    #[Test]
    public function jeder_eintrag_traegt_eine_quelle(): void
    {
        $this->seed(DailyFactSeeder::class);

        $ohneQuelle = DailyFact::query()
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', ''))
            ->pluck('title')
            ->all();

        $this->assertSame([], $ohneQuelle,
            'Ohne Quelle kein Eintrag. Es wird nichts erfunden.');
    }

    #[Test]
    public function kein_eintrag_liegt_auf_einem_datum_das_es_nicht_gibt(): void
    {
        $this->seed(DailyFactSeeder::class);

        $tageJeMonat = DailyEventService::monatstage();
        $ungueltig = [];

        foreach (DailyFact::pluck('month_day', 'id') as $id => $monatTag) {
            if (! preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', (string) $monatTag)) {
                $ungueltig[] = $id.': '.$monatTag;

                continue;
            }
            [$monat, $tag] = explode('-', (string) $monatTag);
            if ((int) $tag > $tageJeMonat[$monat]) {
                $ungueltig[] = $id.': '.$monatTag;
            }
        }

        $this->assertSame([], $ungueltig,
            'Ein Eintrag liegt auf einem Datum, das im Kalender nicht vorkommt.');
    }

    #[Test]
    public function abdeckung_wird_offen_ausgewiesen(): void
    {
        $this->seed(DailyFactSeeder::class);

        $abdeckung = app(DailyEventService::class)->coverage();

        $this->assertSame(366, $abdeckung['total']);
        $this->assertGreaterThanOrEqual(300, $abdeckung['covered'],
            'Die Abdeckung des Kalenderjahres ist zu gering.');

        // Die verbleibenden Luecken muessen benannt sein, nicht verschwiegen.
        $offen = 366 - $abdeckung['covered'];
        $benannt = array_sum(array_map('count', $abdeckung['gaps']));
        $this->assertSame($offen, $benannt,
            'Jeder Tag ohne Eintrag muss in der Lueckenliste erscheinen.');
    }

    #[Test]
    public function fussleiste_zeigt_fuer_einen_belegten_tag_ein_ereignis(): void
    {
        $this->seed(DailyFactSeeder::class);

        // 27.01. ist mit dem Gedenktag doppelt belegt: ein Ereignis und ein
        // Hinweis auf den weiteren Eintrag.
        $ergebnis = app(DailyEventService::class)->forDate(now()->setDate(2026, 1, 27));

        $this->assertNotNull($ergebnis['event']);
        $this->assertNotSame('', (string) $ergebnis['event']->source);
        $this->assertGreaterThanOrEqual(1, $ergebnis['others']->count());
    }
}
