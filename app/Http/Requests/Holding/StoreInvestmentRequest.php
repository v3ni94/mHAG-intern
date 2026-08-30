<?php

namespace App\Http\Requests\Holding;

use App\Support\Money;

/**
 * Beteiligung anlegen/bearbeiten (Abschnitt 84). Der aktuelle interne Wert
 * wird ausschließlich manuell gepflegt und nie automatisch ermittelt.
 */
class StoreInvestmentRequest extends HoldingFormRequest
{
    protected function prepareForValidation(): void
    {
        $percentage = $this->input('share_percentage');
        if (is_string($percentage) && $percentage !== '') {
            $percentage = str_replace([' ', '%'], '', $percentage);
            if (str_contains($percentage, ',')) {
                $percentage = str_replace('.', '', $percentage);
                $percentage = str_replace(',', '.', $percentage);
            }
        }

        $this->merge([
            'share_percentage' => $percentage === '' ? null : $percentage,
            'acquisition_cost' => Money::parse($this->input('acquisition_cost')),
            'current_value' => Money::parse($this->input('current_value')),
        ]);
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
