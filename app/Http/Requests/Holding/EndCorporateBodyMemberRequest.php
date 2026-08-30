<?php

namespace App\Http\Requests\Holding;

/**
 * Mandat beenden (Abschnitt 87): Mitglieder werden NIE gelöscht,
 * nur mit Enddatum und Status "beendet" versehen.
 */
class EndCorporateBodyMemberRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'ended_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ended_on' => 'Mandatsende',
            'note' => 'Notiz',
        ];
    }
}
