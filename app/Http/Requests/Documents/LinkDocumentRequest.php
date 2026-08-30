<?php

namespace App\Http\Requests\Documents;

use App\Http\Controllers\DocumentController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    public function rules(): array
    {
        return [
            'link_type' => ['required', Rule::in(array_keys(DocumentController::LINKABLE_TYPES))],
            'link_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'link_type' => 'Verknüpfungsart',
            'link_id' => 'Verknüpfungsziel',
        ];
    }

    public function messages(): array
    {
        return [
            'link_type.required' => 'Bitte wählen Sie die Verknüpfungsart aus.',
            'link_type.in' => 'Die gewählte Verknüpfungsart ist nicht zulässig.',
            'link_id.required' => 'Bitte geben Sie das Verknüpfungsziel an.',
        ];
    }
}
