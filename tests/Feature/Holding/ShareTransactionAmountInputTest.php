<?php

namespace Tests\Feature\Holding;

use App\Models\ShareTransaction;
use PHPUnit\Framework\Attributes\Test;

/**
 * Betragseingaben bei Aktienbewegungen.
 *
 * Anlass ist ein Befund vom 30.08.2026. Money::parse rundete auf zwei
 * Nachkommastellen, obwohl price_per_share als DECIMAL(18,4) geführt wird.
 * Aus einem Kurs von 12,3456 EUR wurde stillschweigend 12,34 EUR. Bei 10.000
 * Stück sind das 56,00 EUR Unterschied im Gesamtkaufpreis.
 *
 * Zweiter Befund: Eine nicht deutbare Eingabe wurde zu null gemerged. Da die
 * Regel "nullable" lautet, lief der Vorgang durch und die Bewegung entstand
 * ohne Preis, ohne jede Meldung.
 */
class ShareTransactionAmountInputTest extends HoldingTestCase
{
    /** @return array<string, mixed> */
    private function eingabe(array $overrides = []): array
    {
        return array_merge([
            'type' => 'sale',
            'seller_shareholder_id' => $this->timo()->id,
            'buyer_shareholder_id' => $this->newShareholder()->id,
            'share_count' => 10000,
            'total_price' => '',
            'economic_transfer_date' => now()->addDay()->toDateString(),
            'contract_date' => now()->toDateString(),
        ], $overrides);
    }

    #[Test]
    public function vier_nachkommastellen_beim_kurs_je_aktie_bleiben_erhalten(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('share-transactions.store'), $this->eingabe([
            'price_per_share' => '12,3456',
        ]))->assertSessionHasNoErrors();

        $bewegung = ShareTransaction::query()->where('type', 'sale')->latest('id')->firstOrFail();

        $this->assertSame('12.3456', $bewegung->price_per_share);
        // 10.000 x 12,3456 = 123.456,00. Frueher: 10.000 x 12,34 = 123.400,00.
        $this->assertSame('123456.00', $bewegung->total_price);
    }

    #[Test]
    public function tausendertrenner_im_gesamtpreis_wird_richtig_gelesen(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('share-transactions.store'), $this->eingabe([
            'price_per_share' => '',
            'total_price' => '25.000',
        ]))->assertSessionHasNoErrors();

        $bewegung = ShareTransaction::query()->where('type', 'sale')->latest('id')->firstOrFail();

        // Frueher ergab "25.000" den Betrag 25,00 EUR.
        $this->assertSame('25000.00', $bewegung->total_price);
    }

    #[Test]
    public function nicht_deutbarer_kurs_wird_beanstandet_statt_verworfen(): void
    {
        $this->actingAs($this->admin());
        $vorher = ShareTransaction::count();

        $this->post(route('share-transactions.store'), $this->eingabe([
            'price_per_share' => 'ungefähr zwölf',
        ]))->assertSessionHasErrors('price_per_share');

        $this->assertSame($vorher, ShareTransaction::count(),
            'Bei beanstandeter Eingabe darf keine Bewegung entstehen.');
    }

    #[Test]
    public function zu_viele_nachkommastellen_werden_beanstandet_statt_gekuerzt(): void
    {
        $this->actingAs($this->admin());
        $vorher = ShareTransaction::count();

        $this->post(route('share-transactions.store'), $this->eingabe([
            'price_per_share' => '12,34567',
        ]))->assertSessionHasErrors('price_per_share');

        $this->assertSame($vorher, ShareTransaction::count());
    }
}
