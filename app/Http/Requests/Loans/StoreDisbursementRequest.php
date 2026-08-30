<?php

namespace App\Http\Requests\Loans;

use App\Models\BankAccount;
use App\Models\Loan;
use Illuminate\Validation\Validator;

/**
 * Auszahlung planen (Abschnitt 31 Masterprompt) inklusive beider
 * Kontoseiten: von welchem Konto des Darlehensgebers ausgezahlt wurde und
 * auf welches Konto des Darlehensnehmers. Beide Angaben sind optional, weil
 * sie bei nachträglich erfassten Altvorgängen oft nicht mehr bekannt sind.
 * Ein gewähltes Konto MUSS der jeweiligen Partei gehören.
 */
class StoreDisbursementRequest extends LoansFormRequest
{
    protected array $moneyFields = ['planned_amount'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        return [
            'planned_amount' => ['required', 'numeric', 'gt:0'],
            'planned_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'source_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'target_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $loan = Loan::find($this->route('loan'));
            if (! $loan) {
                return;
            }

            $this->assertAccountBelongsTo(
                $validator,
                'source_bank_account_id',
                (int) $loan->lender_entity_id,
                'Das Konto "Ausgezahlt von Konto" gehört nicht zum Darlehensgeber. Bitte ein Konto des Darlehensgebers wählen.',
            );
            $this->assertAccountBelongsTo(
                $validator,
                'target_bank_account_id',
                (int) $loan->borrower_entity_id,
                'Das Konto "Ausgezahlt auf Konto" gehört nicht zum Darlehensnehmer. Bitte ein Konto des Darlehensnehmers wählen.',
            );
        });
    }

    private function assertAccountBelongsTo(Validator $validator, string $field, int $entityId, string $message): void
    {
        $accountId = $this->input($field);
        if (! $accountId) {
            return;
        }
        $belongs = BankAccount::where('id', $accountId)->where('entity_id', $entityId)->exists();
        if (! $belongs) {
            $validator->errors()->add($field, $message);
        }
    }

    public function attributes(): array
    {
        return [
            'planned_amount' => 'Geplanter Betrag',
            'planned_date' => 'Geplantes Datum',
            'reference' => 'Referenz',
            'note' => 'Notiz',
            'source_bank_account_id' => 'Ausgezahlt von Konto',
            'target_bank_account_id' => 'Ausgezahlt auf Konto',
        ];
    }
}
