<?php

namespace App\Http\Requests\MasterData;

class TaxDetailRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull(['tax_id', 'tax_number', 'tax_office', 'note']);
    }

    public function rules(): array
    {
        return [
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tax_id' => 'Steuer-ID',
            'tax_number' => 'Steuernummer',
            'tax_office' => 'Finanzamt',
            'note' => 'Weitere steuerliche Referenzen / Notiz',
        ];
    }
}
