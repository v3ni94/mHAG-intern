<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contracts.update') ?? false;
    }

    public function rules(): array
    {
        $templateId = $this->route('contractTemplate')?->id;

        return [
            'version' => [
                'required', 'string', 'max:20',
                Rule::unique('contract_template_versions', 'version')
                    ->where('contract_template_id', $templateId),
            ],
            'body' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'version' => 'Versionsbezeichnung',
            'body' => 'Vorlagentext',
        ];
    }

    public function messages(): array
    {
        return [
            'version.required' => 'Bitte geben Sie eine Versionsbezeichnung an (z. B. 1.1).',
            'version.unique' => 'Diese Versionsbezeichnung existiert für diese Vorlage bereits.',
            'body.required' => 'Bitte hinterlegen Sie den Vorlagentext. Bestehende Versionen werden nie geändert; es wird immer eine neue Version angelegt.',
        ];
    }
}
