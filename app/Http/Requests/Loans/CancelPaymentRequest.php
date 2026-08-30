<?php

namespace App\Http\Requests\Loans;

/**
 * Zahlungsstorno (Abschnitt 49 Masterprompt): nur mit Grund, kein Löschen.
 */
class CancelPaymentRequest extends LoansFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('payments.cancel');
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['cancel_reason' => 'Stornogrund'];
    }
}
