<?php

namespace App\Http\Requests\Holding;

use Illuminate\Validation\Rule;

/**
 * Manuelle Statuspflege je Unterzeichner (Abschnitt 102) im manuellen
 * Signaturprozess.
 */
class MarkSignatureParticipantRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'participant_id' => ['required', 'integer', 'exists:signature_participants,id'],
            'status' => ['required', Rule::in(['sent', 'opened', 'signed', 'declined', 'expired', 'error'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'participant_id' => 'Unterzeichner',
            'status' => 'Status',
        ];
    }
}
