<?php

namespace Tests\Feature\Loans;

use App\Models\Entity;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gemeinsame Basis fuer die Engine-Tests (Agent B).
 * Kein HTTP, keine Rollen noetig: die Rechen-Engine wird direkt getestet.
 */
abstract class EngineTestCase extends TestCase
{
    use RefreshDatabase;

    protected static int $loanSequence = 0;

    /**
     * Testdarlehen mit Darlehensgeber/-nehmer und Zinstermen.
     * Default: 100.000,00 EUR, Wirkungsbeginn 01.01.2026, ACT/365,
     * monatliche Zinsen, endfaellig, 6 % ab 01.01.2026.
     */
    protected function makeLoan(array $overrides = [], ?array $terms = null): Loan
    {
        $lender = Entity::create(['type' => 'company', 'display_name' => 'Müller Holding AG (Test)']);
        $borrower = Entity::create(['type' => 'person', 'display_name' => 'Test Darlehensnehmer']);

        $loan = Loan::create(array_merge([
            'loan_number' => 'DAR-2026-'.str_pad((string) ++self::$loanSequence, 5, '0', STR_PAD_LEFT),
            'title' => 'Testdarlehen',
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => '2026-01-01',
            'principal_amount' => '100000.00',
            'currency' => 'EUR',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'status' => 'active',
        ], $overrides));

        $terms ??= [['rate' => '6.000000', 'valid_from' => '2026-01-01']];
        foreach ($terms as $term) {
            $loan->interestTerms()->create($term);
        }

        return $loan;
    }

    /** Auszahlung direkt als Darlehenskonto-Buchung erfassen (Kapital +). */
    protected function bookDisbursement(Loan $loan, string $amount, string $effectiveDate): LoanTransaction
    {
        return LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'disbursement',
            'booking_date' => $effectiveDate,
            'effective_date' => $effectiveDate,
            'amount' => $amount,
            'description' => 'Test: Auszahlung',
        ]);
    }

    /** Tilgung direkt als Darlehenskonto-Buchung erfassen (Kapital -). */
    protected function bookRepayment(Loan $loan, string $amount, string $effectiveDate): LoanTransaction
    {
        return LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'repayment',
            'booking_date' => $effectiveDate,
            'effective_date' => $effectiveDate,
            'amount' => '-'.ltrim($amount, '-'),
            'description' => 'Test: Tilgung',
        ]);
    }

    protected function makePayment(Loan $loan, string $amount, string $date, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'loan_id' => $loan->id,
            'payment_date' => $date,
            'amount' => $amount,
            'direction' => 'incoming',
            'origin' => 'bank_import',
            'status' => 'recorded',
        ], $overrides));
    }
}
