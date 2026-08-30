<?php

namespace App\Http\Requests\Loans;

/**
 * Auszahlung planen (Abschnitt 31 Masterprompt).
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
        ];
    }

    public function attributes(): array
    {
        return [
            'planned_amount' => 'Geplanter Betrag',
            'planned_date' => 'Geplantes Datum',
            'reference' => 'Referenz',
            'note' => 'Notiz',
        ];
    }
}
