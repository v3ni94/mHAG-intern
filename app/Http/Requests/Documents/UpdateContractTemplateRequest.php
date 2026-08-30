<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contracts.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'category' => 'Kategorie',
            'description' => 'Beschreibung',
            'is_active' => 'Aktiv',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte geben Sie einen Namen für die Vorlage an.',
        ];
    }
}
