<?php

namespace Tests\Feature\Loans;

use App\Models\LoanTransaction;
use App\Models\Payment;

/**
 * Zahlungen (Abschnitte 46-49 Masterprompt): Erfassung mit Verrechnung,
 * Storno nur mit Grund und Gegenbuchung, kein Löschen.
 */
class UiPaymentTest extends LoansUiTestCase
{
    public function test_zahlung_erfassen_ruft_verrechnung_auf(): void
    {
        $mocks = $this->mockLoanServices();
        $mocks['allocation']->shouldReceive('allocate')
            ->once()
            ->withArgs(fn ($payment, $manual) => (string) $payment->amount === '1234.56' && $manual === null)
            ->andReturn(['interest' => '1234.56']);

        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $response = $this->actingAs($user)->post(route('payments.store'), [
            'loan_id' => $loan->id,
            'payment_date' => now()->toDateString(),
            'amount' => '1.234,56',
            'direction' => 'incoming',
            'origin' => 'bank_import',
            'purpose' => 'Zins August',
        ]);

        $payment = Payment::where('loan_id', $loan->id)->first();
        $this->assertNotNull($payment);
        $response->assertRedirect(route('payments.show', $payment));

        $this->assertSame('1234.56', (string) $payment->amount);
        $this->assertSame('recorded', $payment->status);
        // Standard: Zahler = Darlehensnehmer, Empfänger = Darlehensgeber
        $this->assertSame($loan->borrower_entity_id, $payment->payer_entity_id);
        $this->assertSame($loan->lender_entity_id, $payment->payee_entity_id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payments.recorded']);
    }

    public function test_manuelle_aufteilung_muss_dem_betrag_entsprechen(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $response = $this->actingAs($user)
            ->from(route('payments.create'))
            ->post(route('payments.store'), [
                'loan_id' => $loan->id,
                'payment_date' => now()->toDateString(),
                'amount' => '1.000,00',
                'direction' => 'incoming',
                'origin' => 'manual_entered',
                'allocate_manually' => '1',
                'alloc' => ['interest' => '300,00', 'principal' => '500,00'],
            ]);

        $response->assertSessionHasErrors('alloc');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_manuelle_aufteilung_wird_an_den_service_uebergeben(): void
    {
        $mocks = $this->mockLoanServices();
        $mocks['allocation']->shouldReceive('allocate')
            ->once()
            ->withArgs(function ($payment, $manual) {
                return is_array($manual)
                    && $manual['interest'] === '300.00'
                    && $manual['principal'] === '700.00';
            })
            ->andReturn(['interest' => '300.00', 'principal' => '700.00']);

        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $this->actingAs($user)->post(route('payments.store'), [
            'loan_id' => $loan->id,
            'payment_date' => now()->toDateString(),
            'amount' => '1.000,00',
            'direction' => 'incoming',
            'origin' => 'manual_entered',
            'allocate_manually' => '1',
            'alloc' => ['interest' => '300,00', 'principal' => '700,00'],
        ])->assertRedirect();
    }

    public function test_storno_setzt_status_erzeugt_gegenbuchungen_und_rechnet_neu(): void
    {
        $mocks = $this->mockLoanServices();

        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));
        $payment = $loan->payments()->create([
            'payment_date' => now()->subMonth()->toDateString(),
            'amount' => '500.00',
            'direction' => 'incoming',
            'origin' => 'manual_entered',
            'status' => 'recorded',
        ]);
        $transaction = LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'interest_payment',
            'booking_date' => now()->subMonth()->toDateString(),
            'effective_date' => now()->subMonth()->toDateString(),
            'amount' => '-500.00',
            'description' => 'Zinszahlung',
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->id,
        ]);

        $mocks['recalculation']->shouldReceive('recalculate')
            ->once()
            ->withArgs(fn ($l, $trigger) => $l->id === $loan->id && $trigger === 'payments.cancelled')
            ->andReturn(new \App\Models\LoanRecalculation);

        $response = $this->actingAs($user)->post(route('payments.cancel', $payment), [
            'cancel_reason' => 'Falsch zugeordnet',
        ]);

        $response->assertRedirect(route('payments.show', $payment));

        $payment->refresh();
        $this->assertSame('cancelled', $payment->status);
        $this->assertSame('Falsch zugeordnet', $payment->cancel_reason);
        $this->assertNotNull($payment->cancelled_at);

        // Zahlung bleibt erhalten (kein Löschen), Gegenbuchung mit reversal_of existiert
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('loan_transactions', [
            'loan_id' => $loan->id,
            'booking_type' => 'cancellation',
            'reversal_of' => $transaction->id,
            'amount' => '500.00',
        ]);
        // Ursprüngliche Buchung unverändert vorhanden (append-only)
        $this->assertDatabaseHas('loan_transactions', [
            'id' => $transaction->id,
            'amount' => '-500.00',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payments.cancelled']);
    }

    public function test_storno_erfordert_grund(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));
        $payment = $loan->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => '500.00',
            'direction' => 'incoming',
            'origin' => 'manual_entered',
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)
            ->from(route('payments.show', $payment))
            ->post(route('payments.cancel', $payment), []);

        $response->assertSessionHasErrors('cancel_reason');
        $this->assertSame('recorded', $payment->fresh()->status);
    }

    public function test_zahlungsliste_filtert_nach_herkunft(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));
        $loan->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => '111.00',
            'direction' => 'incoming',
            'origin' => 'bank_import',
            'status' => 'recorded',
        ]);
        $loan->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => '222.00',
            'direction' => 'incoming',
            'origin' => 'manual_entered',
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)->get(route('payments.index', ['origin' => 'bank_import']));

        $response->assertOk();
        $response->assertSee('111,00');
        $response->assertDontSee('222,00');
    }
}
