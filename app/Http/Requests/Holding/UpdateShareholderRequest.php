<?php

namespace App\Http\Requests\Holding;

class UpdateShareholderRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'joined_on' => ['nullable', 'date'],
            'left_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'joined_on' => 'Eintritt',
            'left_on' => 'Austritt',
            'status' => 'Status',
            'notes' => 'Notizen',
        ];
    }
}
