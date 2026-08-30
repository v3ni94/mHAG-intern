<?php

namespace App\Http\Requests\Holding;

use App\Http\Requests\Concerns\ParstDeutscheBetraege;
use Illuminate\Validation\Validator;

/**
 * Beteiligung anlegen/bearbeiten (Abschnitt 84). Der aktuelle interne Wert
 * wird ausschließlich manuell gepflegt und nie automatisch ermittelt.
 */
class StoreInvestmentRequest extends HoldingFormRequest
{
    use ParstDeutscheBetraege;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('share_percentage'))) {
            $this->merge([
                'share_percentage' => str_replace([' ', '%'], '', (string) $this->input('share_percentage')),
            ]);
        }

        // Beteiligungsquote fuehrt sechs Nachkommastellen (DECIMAL(9,6)).
        $this->parstProzent('share_percentage', 6);
        $this->parstBetraege(['acquisition_cost', 'current_value'], 2);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->betragsfehlerMelden($v));
    }

    public function rules(): array
    {
        return [
            'company_entity_id' => ['required', 'integer', 'exists:entities,id'],
            'share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'share_count' => ['nullable', 'integer', 'min:0'],
            'acquired_on' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,sold,liquidated'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_entity_id' => 'Unternehmen',
            'share_percentage' => 'Beteiligungsquote',
            'share_count' => 'Anzahl Anteile',
            'acquired_on' => 'Anschaffungsdatum',
            'acquisition_cost' => 'Anschaffungskosten',
            'current_value' => 'Aktueller interner Wert',
            'status' => 'Beteiligungsstatus',
            'notes' => 'Notizen',
        ];
    }
}
