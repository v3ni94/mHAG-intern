<?php

namespace App\Http\Requests\MasterData;

class AddressRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull([
            'street', 'house_number', 'addition', 'postal_code', 'city', 'state',
            'country', 'valid_from', 'valid_until',
        ]);
        $this->normalizeCheckboxes(['is_primary']);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:main,secondary,business,correspondence,historical'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'addition' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_primary' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Adresstyp',
            'street' => 'Straße',
            'house_number' => 'Hausnummer',
            'addition' => 'Zusatz',
            'postal_code' => 'PLZ',
            'city' => 'Ort',
            'state' => 'Bundesland',
            'country' => 'Land',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'is_primary' => 'Hauptadresse',
        ];
    }

    public function addressData(): array
    {
        $data = $this->validated();
        $data['country'] = $data['country'] ?? 'Deutschland';

        return $data;
    }

    /** Deutsche Bezeichnungen der Adresstypen (auch für Views). */
    public static function typeOptions(): array
    {
        return [
            'main' => 'Hauptwohnsitz / Hauptadresse',
            'secondary' => 'Nebenadresse',
            'business' => 'Geschäftsadresse',
            'correspondence' => 'Korrespondenzadresse',
            'historical' => 'Historische Adresse',
        ];
    }
}
