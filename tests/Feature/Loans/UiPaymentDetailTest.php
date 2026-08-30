<?php

namespace Tests\Feature\Loans;

use App\Enums\AllocationBucket;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use PHPUnit\Framework\Attributes\Test;

/**
 * Zahlungs-Detailseite mit Verrechnungszeilen.
 *
 * Anlass ist ein Befund vom 30.08.2026. Die Ansicht rief
 * AllocationBucket::tryFrom($allocation->bucket) auf, obwohl das Modell das
 * Feld bereits auf das Enum castet. tryFrom erwartet eine Zeichenkette und
 * wirft bei einem Enum-Objekt einen TypeError, der die ganze Seite mitnahm.
 *
 * Warum das keiner Test bemerkt hat: Der bestehende Test rief die Seite nur
 * mit einer Zahlung OHNE Verrechnung auf, dort greift der leere Zweig. Nach
 * jeder über die Anwendung erfassten Zahlung liegt aber mindestens eine
 * Verrechnungszeile vor, und die Anwendung leitet nach dem Erfassen genau auf
 * diese Seite.
 */
class UiPaymentDetailTest extends LoansUiTestCase
{
    #[Test]
    public function detailseite_zeigt_die_verrechnung_einer_zahlung(): void
    {
        $this->mockLoanServices();

        $geber = $this->makeEntity('Müller Holding AG');
        $nehmer = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($geber, $nehmer);

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'payer_entity_id' => $nehmer->id,
            'amount' => '1500.00',
            'payment_date' => '2026-03-01',
            'value_date' => '2026-03-01',
            'direction' => 'incoming',
            'origin' => 'manual_confirmed',
        ]);

        foreach ([
            [AllocationBucket::Interest, '500.00'],
            [AllocationBucket::Principal, '1000.00'],
        ] as [$bucket, $betrag]) {
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'bucket' => $bucket,
                'amount' => $betrag,
            ]);
        }

        $antwort = $this->actingAs($this->makeInternalUser())
            ->get(route('payments.show', $payment));

        $antwort->assertOk()
            ->assertSee('Vertragszinsen')
            ->assertSee('Kapital');
    }

    #[Test]
    public function detailseite_bleibt_ohne_verrechnung_abrufbar(): void
    {
        // Gegenprobe: der bisher einzige geprueffte Fall darf nicht brechen.
        $this->mockLoanServices();

        $geber = $this->makeEntity('Müller Holding AG');
        $nehmer = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($geber, $nehmer);

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'payer_entity_id' => $nehmer->id,
            'amount' => '1500.00',
            'payment_date' => '2026-03-01',
            'value_date' => '2026-03-01',
            'direction' => 'incoming',
            'origin' => 'manual_confirmed',
        ]);

        $this->actingAs($this->makeInternalUser())
            ->get(route('payments.show', $payment))
            ->assertOk();
    }
}
