<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractTemplateRequest extends FormRequest
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
            'version' => ['nullable', 'string', 'max:20'],
            'body' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'category' => 'Kategorie',
            'description' => 'Beschreibung',
            'version' => 'Versionsbezeichnung',
            'body' => 'Vorlagentext',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte geben Sie einen Namen für die Vorlage an.',
            'body.required' => 'Bitte hinterlegen Sie den Vorlagentext (HTML mit Platzhaltern).',
        ];
    }
}
