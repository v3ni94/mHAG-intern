<?php

namespace App\Http\Requests\Loans;

use App\Http\Requests\Concerns\ParstDeutscheBetraege;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ausfall erfassen (Anforderung vom 30.08.2026).
 *
 * Der Grund ist Pflicht: eine Ausfallerfassung ohne Begründung wäre nicht
 * nachvollziehbar. Der Abschreibungsbetrag ist freiwillig; ohne Betrag bleibt
 * die Forderung bestehen und nur der Status ändert sich.
 */
class RecordLoanDefaultRequest extends FormRequest
{
    use ParstDeutscheBetraege;

    protected function prepareForValidation(): void
    {
        // Frueher wurde eine nicht deutbare Eingabe zu null. Da die Regel
        // "nullable" lautet, wurde der Ausfall erfasst und die Abschreibung
        // stillschweigend weggelassen.
        $this->parstBetrag('write_off_amount', 2);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->betragsfehlerMelden($v));
    }

    public function rules(): array
    {
        return [
            'defaulted_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'write_off_amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'defaulted_on' => 'Ausfalldatum',
            'reason' => 'Grund',
            'write_off_amount' => 'Abschreibungsbetrag',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Bitte den Grund des Ausfalls erfassen.',
            'write_off_amount.numeric' => 'Der Abschreibungsbetrag ist kein gültiger Betrag.',
        ];
    }
}
