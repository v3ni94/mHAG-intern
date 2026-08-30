<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanStatus;
use Illuminate\Validation\Rule;

/**
 * Statuswechsel eines Darlehens (Abschnitt 21 Masterprompt):
 * immer über Loan::transitionStatus, Historie bleibt vollständig.
 */
class TransitionLoanRequest extends LoansFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LoanStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'Neuer Status',
            'note' => 'Notiz',
            'effective_date' => 'Wirkungsdatum',
        ];
    }
}
