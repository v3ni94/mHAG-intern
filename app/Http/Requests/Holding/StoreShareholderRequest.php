<?php

namespace App\Http\Requests\Holding;

class StoreShareholderRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'entity_id' => ['required', 'integer', 'exists:entities,id', 'unique:shareholders,entity_id'],
            'shareholder_number' => ['nullable', 'string', 'max:50', 'unique:shareholders,shareholder_number'],
            'joined_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'entity_id' => 'Person oder Unternehmen',
            'shareholder_number' => 'Aktionärsnummer',
            'joined_on' => 'Eintritt',
            'notes' => 'Notizen',
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'entity_id.unique' => 'Für diese Person bzw. dieses Unternehmen existiert bereits ein Aktionär.',
        ]);
    }
}
