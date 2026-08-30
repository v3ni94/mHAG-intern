<?php

namespace Tests\Feature\Loans;

/**
 * Datenscope (Abschnitt 14 Masterprompt): Externe Rollen sehen nur
 * Darlehen ihrer zugeordneten Entities; interne Felder bleiben intern.
 */
class UiLoanScopingTest extends LoansUiTestCase
{
    public function test_darlehensnehmer_sieht_nur_eigene_darlehen_in_der_liste(): void
    {
        $this->mockLoanServices();
        $lender = $this->makeEntity('Müller Holding AG');
        $eigeneFirma = $this->makeEntity('Eigene GmbH');
        $fremdeFirma = $this->makeEntity('Fremde GmbH');

        $eigenes = $this->makeLoan($lender, $eigeneFirma);
        $fremdes = $this->makeLoan($lender, $fremdeFirma);

        $user = $this->makeExternalUser('Darlehensnehmer', $eigeneFirma);

        $response = $this->actingAs($user)->get(route('loans.index'));

        $response->assertOk();
        $response->assertSee($eigenes->loan_number);
        $response->assertDontSee($fremdes->loan_number);
    }

    public function test_fremdes_darlehen_liefert_404_fuer_externe(): void
    {
        $this->mockLoanServices();
        $lender = $this->makeEntity('Müller Holding AG');
        $eigeneFirma = $this->makeEntity('Eigene GmbH');
        $fremdeFirma = $this->makeEntity('Fremde GmbH');
        $fremdes = $this->makeLoan($lender, $fremdeFirma);

        $user = $this->makeExternalUser('Darlehensnehmer', $eigeneFirma);

        $this->actingAs($user)->get(route('loans.show', $fremdes))->assertNotFound();
    }

    public function test_eigenes_darlehen_ist_fuer_darlehensnehmer_sichtbar_ohne_interne_felder(): void
    {
        $this->mockLoanServices();
        $lender = $this->makeEntity('Müller Holding AG');
        $eigeneFirma = $this->makeEntity('Eigene GmbH');
        $loan = $this->makeLoan($lender, $eigeneFirma, [
            'internal_notes' => 'STRENG-INTERN-Bonität fraglich',
            'risk_rating' => 'high',
        ]);

        $user = $this->makeExternalUser('Darlehensnehmer', $eigeneFirma);

        $response = $this->actingAs($user)->get(route('loans.show', $loan));

        $response->assertOk();
        $response->assertSee($loan->loan_number);
        // Interne Notizen und Risiko-Einstufung erscheinen NICHT im HTML externer Rollen
        $response->assertDontSee('STRENG-INTERN-Bonität fraglich');
        $response->assertDontSee('Risiko und interne Notizen');
    }

    public function test_interne_rolle_sieht_interne_notizen(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser('Sachbearbeiter');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'), [
            'internal_notes' => 'STRENG-INTERN-Bonität fraglich',
        ]);

        $response = $this->actingAs($user)->get(route('loans.show', $loan));

        $response->assertOk();
        $response->assertSee('STRENG-INTERN-Bonität fraglich');
    }

    public function test_risikospalte_der_liste_nur_fuer_interne(): void
    {
        $this->mockLoanServices();
        $lender = $this->makeEntity('Müller Holding AG');
        $eigeneFirma = $this->makeEntity('Eigene GmbH');
        $this->makeLoan($lender, $eigeneFirma, ['risk_rating' => 'high']);

        $extern = $this->makeExternalUser('Darlehensnehmer', $eigeneFirma);
        $this->actingAs($extern)->get(route('loans.index'))->assertDontSee('Risiko');

        $intern = $this->makeInternalUser();
        $this->actingAs($intern)->get(route('loans.index'))->assertSee('Risiko');
    }

    public function test_externe_ohne_create_berechtigung_erhalten_403(): void
    {
        $this->mockLoanServices();
        $firma = $this->makeEntity('Eigene GmbH');
        $user = $this->makeExternalUser('Darlehensnehmer', $firma);

        $this->actingAs($user)->get(route('loans.create'))->assertForbidden();
        $this->actingAs($user)->post(route('loans.store'), [])->assertForbidden();
    }

    public function test_zahlungen_sind_auf_sichtbare_darlehen_gescoped(): void
    {
        $this->mockLoanServices();
        $lender = $this->makeEntity('Müller Holding AG');
        $eigeneFirma = $this->makeEntity('Eigene GmbH');
        $fremdeFirma = $this->makeEntity('Fremde GmbH');

        $eigenes = $this->makeLoan($lender, $eigeneFirma);
        $fremdes = $this->makeLoan($lender, $fremdeFirma);

        $eigeneZahlung = $eigenes->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => '111.11',
            'direction' => 'incoming',
            'origin' => 'manual_entered',
            'status' => 'recorded',
        ]);
        $fremdeZahlung = $fremdes->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => '222.22',
            'direction' => 'incoming',
            'origin' => 'manual_entered',
            'status' => 'recorded',
        ]);

        $user = $this->makeExternalUser('Darlehensnehmer', $eigeneFirma);

        $response = $this->actingAs($user)->get(route('payments.index'));
        $response->assertOk();
        $response->assertSee('111,11');
        $response->assertDontSee('222,22');

        $this->actingAs($user)->get(route('payments.show', $fremdeZahlung))->assertNotFound();
        $this->actingAs($user)->get(route('payments.show', $eigeneZahlung))->assertOk();
    }
}
