<?php

namespace App\Http\Requests\MasterData;

use App\Enums\IdentityDocumentType;
use Illuminate\Validation\Rule;

class IdentityDocumentRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull([
            'document_number', 'issued_on', 'expires_on', 'authority', 'country', 'note',
        ]);
        $this->normalizeCheckboxes(['verified']);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IdentityDocumentType::class)],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'authority' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'verified' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Dokumenttyp',
            'document_number' => 'Dokumentnummer',
            'issued_on' => 'Ausstellungsdatum',
            'expires_on' => 'Ablaufdatum',
            'authority' => 'Ausstellende Behörde',
            'country' => 'Land',
            'verified' => 'Geprüft',
            'note' => 'Notiz',
        ];
    }
}
