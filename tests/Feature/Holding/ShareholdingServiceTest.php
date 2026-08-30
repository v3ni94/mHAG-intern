<?php

namespace Tests\Feature\Holding;

use App\Enums\ShareTransactionStatus;
use App\Enums\ShareTransactionType;
use App\Models\ShareTransaction;
use App\Services\Holding\ShareholdingService;
use Illuminate\Validation\ValidationException;

/**
 * Aktienlogik gem. Abschnitt 139 Masterprompt: Verkauf, Kauf, Storno,
 * historische Aktionärsstruktur, Prozentberechnung, mehrere Transaktionen.
 */
class ShareholdingServiceTest extends HoldingTestCase
{
    private ShareholdingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShareholdingService::class);
        $this->actingAs($this->admin());
    }

    public function test_ausgangsbestand_timo_haelt_alle_aktien(): void
    {
        $holdings = $this->service->holdingsAsOf();

        $this->assertCount(1, $holdings);
        $this->assertSame(100000, $holdings[0]['shares']);
        $this->assertSame('100.000000', $holdings[0]['percentage']);
        $this->assertSame($this->timo()->id, $holdings[0]['shareholder']->id);
        $this->assertSame(100000, $this->service->totalShares());
    }

    public function test_verkauf_30000_an_neuaktionaer_ergibt_70_zu_30(): void
    {
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $sale = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->addDay()->toDateString(),
        ]);

        $this->service->makeEffective($sale);

        $this->assertSame(ShareTransactionStatus::Effective, $sale->fresh()->status);

        $holdings = $this->service->holdingsAsOf(now()->addDay());
        $byId = $holdings->keyBy(fn (array $row) => $row['shareholder']->id);

        $this->assertSame(70000, $byId[$timo->id]['shares']);
        $this->assertSame('70.000000', $byId[$timo->id]['percentage']);
        $this->assertSame(30000, $byId[$buyer->id]['shares']);
        $this->assertSame('30.000000', $byId[$buyer->id]['percentage']);

        // Sortierung nach Bestand absteigend
        $this->assertSame($timo->id, $holdings[0]['shareholder']->id);
    }

    public function test_rueckkauf_erhoeht_bestand_des_kaeufers_wieder(): void
    {
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $sale = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->addDay()->toDateString(),
        ]);
        $this->service->makeEffective($sale);

        $buyBack = $this->makeTransaction([
            'type' => 'purchase',
            'seller_shareholder_id' => $buyer->id,
            'buyer_shareholder_id' => $timo->id,
            'share_count' => 10000,
            'economic_transfer_date' => now()->addDays(2)->toDateString(),
        ]);
        $this->service->makeEffective($buyBack);

        $this->assertSame(80000, $this->service->sharesOf($timo, now()->addDays(2)));
        $this->assertSame(20000, $this->service->sharesOf($buyer, now()->addDays(2)));

        // Vor dem Rückkauf bleibt der Zwischenstand erhalten
        $this->assertSame(70000, $this->service->sharesOf($timo, now()->addDay()));
    }

    public function test_storno_per_gegenbuchung_stellt_bestand_wieder_her_und_erhoeht_transaktionszahl(): void
    {
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $sale = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->toDateString(),
        ]);
        $this->service->makeEffective($sale);
        $this->assertSame(70000, $this->service->sharesOf($timo));

        $countBefore = ShareTransaction::count();

        $reversal = $this->service->cancel($sale->fresh());

        // Gegenbuchung: neue Transaktion, Original bleibt bestehen (nie löschen)
        $this->assertSame($countBefore + 1, ShareTransaction::count());
        $this->assertNotSame($sale->id, $reversal->id);
        $this->assertSame(ShareTransactionType::Correction, $reversal->type);
        $this->assertSame($sale->id, $reversal->reversal_of);
        $this->assertSame(ShareTransactionStatus::Effective, $reversal->status);
        $this->assertDatabaseHas('share_transactions', ['id' => $sale->id]);

        // Bestand ist wiederhergestellt
        $this->assertSame(100000, $this->service->sharesOf($timo));
        $this->assertSame(0, $this->service->sharesOf($buyer));

        // Doppeltes Storno ist ausgeschlossen
        $this->expectException(ValidationException::class);
        $this->service->cancel($sale->fresh());
    }

    public function test_storno_einer_nicht_wirksamen_transaktion_setzt_status_storniert(): void
    {
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $draft = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 500,
        ]);

        $countBefore = ShareTransaction::count();
        $result = $this->service->cancel($draft);

        $this->assertSame($draft->id, $result->id);
        $this->assertSame(ShareTransactionStatus::Cancelled, $result->status);
        $this->assertSame($countBefore, ShareTransaction::count());
    }

    public function test_historischer_stichtag_vor_und_nach_uebergang(): void
    {
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $sale = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->service->makeEffective($sale);

        // Vor dem wirtschaftlichen Übergang: unverändert
        $before = $this->service->holdingsAsOf(now())->keyBy(fn (array $r) => $r['shareholder']->id);
        $this->assertSame(100000, $before[$timo->id]['shares']);
        $this->assertArrayNotHasKey($buyer->id, $before->all());

        // Ab dem Übergang: 70/30
        $after = $this->service->holdingsAsOf(now()->addDays(10))->keyBy(fn (array $r) => $r['shareholder']->id);
        $this->assertSame(70000, $after[$timo->id]['shares']);
        $this->assertSame(30000, $after[$buyer->id]['shares']);
    }

    public function test_verkaeufer_ohne_deckung_wird_abgelehnt(): void
    {
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $sale = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 130000,
        ]);

        try {
            $this->service->makeEffective($sale);
            $this->fail('Erwartet: ValidationException wegen fehlender Verkäuferdeckung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('seller_shareholder_id', $e->errors());
            $this->assertStringContainsString('nicht über genügend Aktien', $e->errors()['seller_shareholder_id'][0]);
        }

        // Status unverändert, Bestand unverändert
        $this->assertSame(ShareTransactionStatus::Draft, $sale->fresh()->status);
        $this->assertSame(100000, $this->service->sharesOf($timo));
    }

    public function test_deckungspruefung_beruecksichtigt_bereits_wirksame_spaetere_verkaeufe(): void
    {
        $timo = $this->timo();
        $b = $this->newShareholder('Aktionär B', 'AKT-T00B');
        $c = $this->newShareholder('Aktionär C', 'AKT-T00C');

        // B erhält 30.000 (wirksam ab morgen)
        $this->service->makeEffective($this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $b->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->addDay()->toDateString(),
        ]));

        // B verkauft bereits wirksam 25.000 an C zum Tag +5
        $this->service->makeEffective($this->makeTransaction([
            'seller_shareholder_id' => $b->id,
            'buyer_shareholder_id' => $c->id,
            'share_count' => 25000,
            'economic_transfer_date' => now()->addDays(5)->toDateString(),
        ]));

        // Ein weiterer Verkauf von 10.000 zum Tag +2 würde den Bestand ab Tag +5 negativ machen
        $overdraw = $this->makeTransaction([
            'seller_shareholder_id' => $b->id,
            'buyer_shareholder_id' => $c->id,
            'share_count' => 10000,
            'economic_transfer_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->makeEffective($overdraw);
    }

    public function test_kumulation_mehrerer_transaktionen_und_prozentberechnung(): void
    {
        $timo = $this->timo();
        $b = $this->newShareholder('Aktionär B', 'AKT-T00B');
        $c = $this->newShareholder('Aktionär C', 'AKT-T00C');

        $this->service->makeEffective($this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $b->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->addDay()->toDateString(),
        ]));
        $this->service->makeEffective($this->makeTransaction([
            'type' => 'transfer',
            'seller_shareholder_id' => $b->id,
            'buyer_shareholder_id' => $c->id,
            'share_count' => 10000,
            'economic_transfer_date' => now()->addDays(2)->toDateString(),
        ]));
        $this->service->makeEffective($this->makeTransaction([
            'type' => 'purchase',
            'seller_shareholder_id' => $b->id,
            'buyer_shareholder_id' => $timo->id,
            'share_count' => 5000,
            'economic_transfer_date' => now()->addDays(3)->toDateString(),
        ]));

        $holdings = $this->service->holdingsAsOf(now()->addDays(3))->keyBy(fn (array $r) => $r['shareholder']->id);

        $this->assertSame(75000, $holdings[$timo->id]['shares']);
        $this->assertSame('75.000000', $holdings[$timo->id]['percentage']);
        $this->assertSame(15000, $holdings[$b->id]['shares']);
        $this->assertSame('15.000000', $holdings[$b->id]['percentage']);
        $this->assertSame(10000, $holdings[$c->id]['shares']);
        $this->assertSame('10.000000', $holdings[$c->id]['percentage']);

        // Summe bleibt vollständig
        $this->assertSame(100000, $holdings->sum(fn (array $r) => $r['shares']));
    }

    public function test_kapitalerhoehung_ueber_gesamtaktienzahl_wird_abgelehnt(): void
    {
        $timo = $this->timo();

        $increase = $this->makeTransaction([
            'type' => 'capital_increase',
            'seller_shareholder_id' => null,
            'buyer_shareholder_id' => $timo->id,
            'share_count' => 1,
        ]);

        try {
            $this->service->makeEffective($increase);
            $this->fail('Erwartet: ValidationException wegen Kapitalgrenze.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('share_count', $e->errors());
        }
    }

    public function test_bereits_wirksame_transaktion_kann_nicht_erneut_wirksam_gesetzt_werden(): void
    {
        $initial = ShareTransaction::query()
            ->where('transaction_number', 'AB-INITIAL-0001')
            ->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->service->makeEffective($initial);
    }
}
