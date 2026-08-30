<?php

namespace Tests\Feature\Holding;

use App\Enums\ShareTransactionStatus;
use App\Models\ShareTransaction;

/**
 * Aktienbewegungen über die Oberfläche: Register, Erfassung mit
 * automatischem Gesamtpreis, Wirksamsetzung (shares.finalize) und Storno.
 */
class ShareTransactionHttpTest extends HoldingTestCase
{
    public function test_register_zeigt_bewegungen_und_filtert_nach_status(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('share-transactions.index'))
            ->assertOk()
            ->assertSee('AB-INITIAL-0001')
            ->assertSee('Kapitalerhöhung');

        $this->get(route('share-transactions.index', ['status' => 'draft']))
            ->assertOk()
            ->assertDontSee('AB-INITIAL-0001');
    }

    public function test_erfassung_berechnet_gesamtpreis_automatisch_aus_deutscher_eingabe(): void
    {
        $this->actingAs($this->admin());
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $response = $this->post(route('share-transactions.store'), [
            'type' => 'sale',
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'price_per_share' => '12,50',
            'total_price' => '',
            'economic_transfer_date' => now()->addDay()->toDateString(),
            'contract_date' => now()->toDateString(),
        ]);

        $transaction = ShareTransaction::query()->where('type', 'sale')->latest('id')->firstOrFail();
        $response->assertRedirect(route('share-transactions.show', $transaction));

        $this->assertSame(ShareTransactionStatus::Draft, $transaction->status);
        $this->assertMatchesRegularExpression('/^AB-\d{4}-\d{5}$/', $transaction->transaction_number);
        $this->assertSame('375000.00', $transaction->total_price);
        $this->assertSame('12.5000', $transaction->price_per_share);

        // Entwurf verändert den Bestand nicht
        $service = app(\App\Services\Holding\ShareholdingService::class);
        $this->assertSame(100000, $service->sharesOf($timo, now()->addDay()));
    }

    public function test_wirksamsetzung_ueber_http_mit_berechtigung(): void
    {
        $this->actingAs($this->admin());
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $transaction = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->toDateString(),
        ]);

        $this->post(route('share-transactions.make-effective', $transaction))
            ->assertRedirect(route('share-transactions.show', $transaction));

        $this->assertSame(ShareTransactionStatus::Effective, $transaction->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'share-transactions.made-effective']);
    }

    public function test_wirksamsetzung_ohne_deckung_liefert_validierungsfehler(): void
    {
        $this->actingAs($this->admin());
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $transaction = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 200000,
        ]);

        $this->from(route('share-transactions.show', $transaction))
            ->post(route('share-transactions.make-effective', $transaction))
            ->assertSessionHasErrors('seller_shareholder_id');

        $this->assertSame(ShareTransactionStatus::Draft, $transaction->fresh()->status);
    }

    public function test_wirksamsetzung_und_storno_erfordern_shares_finalize(): void
    {
        $this->actingAs($this->readOnlyUser());
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $transaction = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 100,
        ]);

        $this->post(route('share-transactions.make-effective', $transaction))->assertForbidden();
        $this->post(route('share-transactions.cancel', $transaction))->assertForbidden();

        // Lesen ist erlaubt
        $this->get(route('share-transactions.index'))->assertOk();
        $this->get(route('share-transactions.show', $transaction))->assertOk();

        // Erfassen erfordert shares.prepare
        $this->get(route('share-transactions.create'))->assertForbidden();
    }

    public function test_storno_ueber_http_erzeugt_gegenbuchung(): void
    {
        $this->actingAs($this->admin());
        $timo = $this->timo();
        $buyer = $this->newShareholder();

        $transaction = $this->makeTransaction([
            'seller_shareholder_id' => $timo->id,
            'buyer_shareholder_id' => $buyer->id,
            'share_count' => 30000,
            'economic_transfer_date' => now()->subDay()->toDateString(),
            'status' => ShareTransactionStatus::Effective->value,
        ]);

        $countBefore = ShareTransaction::count();

        $this->post(route('share-transactions.cancel', $transaction))
            ->assertRedirect(route('share-transactions.show', $transaction));

        $this->assertSame($countBefore + 1, ShareTransaction::count());
        $reversal = ShareTransaction::query()->where('reversal_of', $transaction->id)->firstOrFail();
        $this->assertSame('correction', $reversal->type->value);

        $service = app(\App\Services\Holding\ShareholdingService::class);
        $this->assertSame(100000, $service->sharesOf($timo));
    }
}
