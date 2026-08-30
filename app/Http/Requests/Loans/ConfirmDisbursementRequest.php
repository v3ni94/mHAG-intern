<?php

namespace App\Http\Requests\Loans;

use App\Enums\PaymentOrigin;
use Illuminate\Validation\Rule;

/**
 * Auszahlung bestätigen (Abschnitt 31 Masterprompt): IST-Betrag, Datum,
 * Herkunft des IST-Wertes (Abschnitt 25).
 */
class ConfirmDisbursementRequest extends LoansFormRequest
{
    protected array $moneyFields = ['actual_amount'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('payments.record');
    }

    public function rules(): array
    {
        return [
            'actual_amount' => ['required', 'numeric', 'gt:0'],
            'actual_date' => ['required', 'date'],
            'origin' => ['required', Rule::in([
                PaymentOrigin::ManualConfirmed->value,
                PaymentOrigin::ManualEntered->value,
                PaymentOrigin::BankImport->value,
            ])],
        ];
    }

    public function attributes(): array
    {
        return [
            'actual_amount' => 'IST-Betrag',
            'actual_date' => 'Tatsächliches Datum',
            'origin' => 'Herkunft',
        ];
    }
}
