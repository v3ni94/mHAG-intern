<?php

namespace Tests\Feature\Loans;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanTransaction;
use Illuminate\Support\Carbon;

/**
 * Oberfläche "Ausfall erfassen" (Anforderung vom 30.08.2026).
 */
class UiLoanDefaultTest extends LoansUiTestCase
{
    private function makeDisbursedLoan(): Loan
    {
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($lender, $borrower, [
            'effective_from' => '2026-01-01',
            'contract_end' => '2026-12-31',
            'principal_amount' => '100000.00',
        ]);
        $loan->interestTerms()->create(['rate' => '6.000000', 'valid_from' => '2026-01-01']);
        LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'disbursement',
            'booking_date' => '2026-01-01',
            'effective_date' => '2026-01-01',
            'amount' => '100000.00',
            'description' => 'Auszahlung',
        ]);

        return $loan->fresh();
    }

    public function test_formular_ist_auf_der_detailseite_erreichbar(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->get(route('loans.show', $loan));

        $response->assertOk();
        $response->assertSee('Ausfall erfassen');
        $response->assertSee('Ausfalldatum (Wirkungsdatum)');
        $response->assertSee('Ohne Betrag bleibt die Forderung bestehen');
        $response->assertSee('Freigabe durch die Geschäftsführung einholen');
    }

    public function test_erfassung_setzt_status_datum_und_abschreibung(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 09:00:00'));
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => 'Insolvenzantrag gestellt',
            'write_off_amount' => '25.000,00',
        ]);

        $response->assertRedirect(route('loans.show', $loan));
        $loan = $loan->fresh();
        $this->assertSame(LoanStatus::Defaulted, $loan->status);
        $this->assertSame('2026-05-15', $loan->defaulted_on->toDateString());
        $this->assertDatabaseHas('loan_transactions', [
            'loan_id' => $loan->id,
            'booking_type' => 'write_off',
            'amount' => '-25000.00',
        ]);
    }

    public function test_nicht_deutbarer_abschreibungsbetrag_wird_beanstandet(): void
    {
        /*
         * Befund vom 30.08.2026: Money::parse gab bei einer nicht deutbaren
         * Eingabe null zurueck, und dieses null wurde in die Anfrage gemergt.
         * Die Regel lautet "nullable", der Vorgang lief also durch: Status
         * gewechselt, Abschreibung nicht gebucht, keine Meldung. Die Forderung
         * blieb in voller Hoehe stehen und wirkte so in Forderungsaufstellung,
         * Kennzahlen und Verzugszinsgrundlage weiter.
         */
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => 'Insolvenzantrag gestellt',
            'write_off_amount' => '25.000,-',
        ]);

        $response->assertSessionHasErrors('write_off_amount');
        $this->assertNull($loan->fresh()->defaulted_on,
            'Der Ausfall darf nicht erfasst werden, wenn die Abschreibung nicht lesbar ist.');
        $this->assertDatabaseMissing('loan_transactions', [
            'loan_id' => $loan->id,
            'booking_type' => 'write_off',
        ]);
    }

    public function test_abschreibungsbetrag_mit_zu_vielen_nachkommastellen_wird_beanstandet(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => 'Insolvenzantrag gestellt',
            'write_off_amount' => '25000,123',
        ]);

        $response->assertSessionHasErrors('write_off_amount');
        $this->assertNull($loan->fresh()->defaulted_on);
    }

    public function test_abschreibungsbetrag_mit_tausendertrenner_ohne_komma(): void
    {
        // "25.000" bedeutet fuenfundzwanzigtausend. Frueher wurden daraus
        // 25,00 EUR gebucht, ein um den Faktor 1000 falscher Betrag.
        Carbon::setTestNow(Carbon::parse('2026-08-30 09:00:00'));
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => 'Insolvenzantrag gestellt',
            'write_off_amount' => '25.000',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loan_transactions', [
            'loan_id' => $loan->id,
            'booking_type' => 'write_off',
            'amount' => '-25000.00',
        ]);
    }

    public function test_erfassung_verlangt_einen_grund(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertNull($loan->fresh()->defaulted_on);
    }

    public function test_negativer_abschreibungsbetrag_wird_abgewiesen(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => 'Test',
            'write_off_amount' => '-1.000,00',
        ]);

        $response->assertSessionHasErrors('write_off_amount');
    }

    public function test_ruecknahme_setzt_den_status_zurueck(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 09:00:00'));
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $this->actingAs($user)->post(route('loans.default.record', $loan), [
            'defaulted_on' => '2026-05-15',
            'reason' => 'Insolvenzantrag gestellt',
            'write_off_amount' => '25.000,00',
        ]);

        $response = $this->actingAs($user)->post(route('loans.default.revoke', $loan->fresh()), [
            'note' => 'Vergleich geschlossen',
            'reverse_write_off' => '1',
        ]);

        $response->assertRedirect(route('loans.show', $loan));
        $loan = $loan->fresh();
        $this->assertNull($loan->defaulted_on);
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertDatabaseHas('loan_transactions', [
            'loan_id' => $loan->id,
            'booking_type' => 'cancellation',
            'amount' => '25000.00',
        ]);
    }

    public function test_ruecknahme_ohne_erfassten_ausfall_wird_abgewiesen(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeDisbursedLoan();

        $response = $this->actingAs($user)->post(route('loans.default.revoke', $loan), []);

        $response->assertSessionHas('danger');
    }
}
