<?php

namespace App\Http\Requests\Loans;

/**
 * Zinssatz-Staffel (Abschnitt 40 Masterprompt): historisierte Zinssätze,
 * zinslos = 0. Änderung löst Neuberechnung aus.
 */
class StoreInterestTermRequest extends LoansFormRequest
{
    protected array $percentFields = ['rate'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        return [
            'rate' => ['required', 'numeric', 'gte:0', 'max:100'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rate' => 'Zinssatz (% p. a.)',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'note' => 'Notiz',
        ];
    }
}
