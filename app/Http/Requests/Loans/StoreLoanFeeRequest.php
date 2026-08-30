<?php

namespace App\Http\Requests\Loans;

use App\Enums\FeeType;
use Illuminate\Validation\Rule;

/**
 * Gebühren (Abschnitt 43 Masterprompt): fester Betrag oder prozentual,
 * einmalig oder wiederkehrend.
 */
class StoreLoanFeeRequest extends LoansFormRequest
{
    protected array $moneyFields = ['amount'];

    protected array $percentFields = ['percentage'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(FeeType::class)],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'required_without:percentage', 'numeric', 'gt:0'],
            'percentage' => ['nullable', 'required_without:amount', 'numeric', 'gt:0', 'max:100'],
            'recurrence' => ['required', Rule::in(['one_time', 'monthly', 'quarterly', 'annual'])],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'required_without' => 'Bitte entweder einen festen Betrag oder einen Prozentsatz angeben.',
        ]);
    }

    public function attributes(): array
    {
        return [
            'type' => 'Gebührenart',
            'name' => 'Bezeichnung',
            'amount' => 'Betrag',
            'percentage' => 'Prozentsatz',
            'recurrence' => 'Wiederkehr',
            'due_date' => 'Fälligkeitsdatum',
        ];
    }
}
