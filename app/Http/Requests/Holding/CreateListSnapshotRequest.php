<?php

namespace App\Http\Requests\Holding;

class CreateListSnapshotRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'as_of' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return ['as_of' => 'Stichtag'];
    }
}
