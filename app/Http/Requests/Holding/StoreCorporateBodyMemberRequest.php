<?php

namespace App\Http\Requests\Holding;

class StoreCorporateBodyMemberRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'person_entity_id' => ['required', 'integer', 'exists:entities,id'],
            'role' => ['required', 'string', 'max:100'],
            'is_chair' => ['nullable', 'boolean'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'representation' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'person_entity_id' => 'Person',
            'role' => 'Rolle',
            'is_chair' => 'Vorsitz',
            'started_on' => 'Beginn',
            'ended_on' => 'Ende',
            'representation' => 'Vertretungsbefugnis',
            'note' => 'Notiz',
        ];
    }
}
