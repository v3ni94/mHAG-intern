<?php

namespace App\Http\Requests\MasterData;

/**
 * Aktualisiert nur Annotationsfelder einer Organstellung. Rolle, Person und
 * Unternehmen sind aus Gründen der Historientreue unveränderlich; das Ende
 * einer Stellung läuft ausschließlich über die Beenden-Aktion.
 */
class UpdateOrganizationRoleRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull(['started_on', 'representation_rule', 'note']);
        $this->normalizeCheckboxes(['sole_representation', 'exemption_181']);
    }

    public function rules(): array
    {
        return [
            'started_on' => ['nullable', 'date'],
            'sole_representation' => ['boolean'],
            'representation_rule' => ['nullable', 'string', 'max:255'],
            'exemption_181' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'started_on' => 'Beginn',
            'sole_representation' => 'Einzelvertretung',
            'representation_rule' => 'Vertretungsregel',
            'exemption_181' => 'Befreiung von § 181 BGB',
            'note' => 'Notiz',
        ];
    }
}
