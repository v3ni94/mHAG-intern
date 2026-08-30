<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractAmendmentRequest extends FormRequest
{
    /** Nachtragstypen (Abschnitt 56 Masterprompt) mit deutschen Labels. */
    public const AMENDMENT_TYPES = [
        'term_extension' => 'Laufzeitverlängerung',
        'rate_change' => 'Zinssatzänderung',
        'repayment_change' => 'Tilgungsänderung',
        'deferral' => 'Stundung',
        'principal_change' => 'Kapitaländerung',
        'security_change' => 'Sicherheitenänderung',
        'other' => 'Sonstiger Nachtrag',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('contracts.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'amendment_type' => ['required', Rule::in(array_keys(self::AMENDMENT_TYPES))],
            'description' => ['required', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amendment_type' => 'Nachtragstyp',
            'description' => 'Beschreibung',
            'effective_date' => 'Wirkungsdatum',
        ];
    }

    public function messages(): array
    {
        return [
            'amendment_type.required' => 'Bitte wählen Sie den Nachtragstyp aus.',
            'amendment_type.in' => 'Der gewählte Nachtragstyp ist nicht zulässig.',
            'description.required' => 'Bitte beschreiben Sie den Inhalt des Nachtrags.',
        ];
    }
}
