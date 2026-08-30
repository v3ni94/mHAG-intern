<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contracts.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'contract_template_version_id' => ['required', 'integer', 'exists:contract_template_versions,id'],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
            'title' => ['required', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contract_template_version_id' => 'Vorlagenversion',
            'loan_id' => 'Darlehen',
            'title' => 'Titel',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_template_version_id.required' => 'Bitte wählen Sie eine Vorlagenversion aus.',
            'contract_template_version_id.exists' => 'Die gewählte Vorlagenversion existiert nicht.',
            'loan_id.exists' => 'Das gewählte Darlehen existiert nicht.',
            'title.required' => 'Bitte geben Sie einen Titel für den Vertrag an.',
        ];
    }
}
