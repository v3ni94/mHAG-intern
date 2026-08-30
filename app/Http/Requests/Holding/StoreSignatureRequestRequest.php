<?php

namespace App\Http\Requests\Holding;

use App\Http\Controllers\SignatureRequestController;
use Illuminate\Validation\Rule;

/**
 * Signaturanfrage erstellen (Abschnitt 100): Vorgang, PDF und Unterzeichner.
 */
class StoreSignatureRequestRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'subject_type' => ['required', Rule::in(array_keys(SignatureRequestController::SUBJECT_TYPES))],
            'subject_id' => ['required', 'integer', 'min:1'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'participants.*.role' => ['nullable', 'string', 'max:100'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
            'send_immediately' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'subject_type' => 'Vorgangsart',
            'subject_id' => 'Vorgang',
            'participants' => 'Unterzeichner',
            'participants.*.entity_id' => 'Unterzeichner',
            'participants.*.role' => 'Unterzeichnerrolle',
            'participants.*.email' => 'E-Mail-Adresse',
        ];
    }
}
