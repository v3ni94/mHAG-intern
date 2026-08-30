<?php

namespace App\Http\Requests\MasterData;

use App\Enums\EntityType;
use App\Enums\OrganizationRoleType;
use App\Models\Entity;
use App\Models\OrganizationRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Neue Organstellung (Abschnitt 7): aus Sicht des Unternehmens wird die
 * Person gewählt, aus Sicht der Person das Unternehmen. Historische
 * Organstellungen werden nie überschrieben.
 */
class OrganizationRoleRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull([
            'person_entity_id', 'company_entity_id', 'started_on',
            'representation_rule', 'note',
        ]);
        $this->normalizeCheckboxes(['sole_representation', 'exemption_181']);

        // Fehlende Seite aus der Akte ergänzen.
        $entity = $this->entity();
        if ($entity->type === EntityType::Company && ! $this->filled('company_entity_id')) {
            $this->merge(['company_entity_id' => $entity->id]);
        }
        if ($entity->type === EntityType::Person && ! $this->filled('person_entity_id')) {
            $this->merge(['person_entity_id' => $entity->id]);
        }
    }

    public function rules(): array
    {
        return [
            'company_entity_id' => [
                'required', 'integer',
                Rule::exists('entities', 'id')->whereNull('deleted_at'),
            ],
            'person_entity_id' => [
                'required', 'integer',
                Rule::exists('entities', 'id')->whereNull('deleted_at'),
            ],
            'role' => ['required', Rule::enum(OrganizationRoleType::class)],
            'started_on' => ['nullable', 'date'],
            'sole_representation' => ['boolean'],
            'representation_rule' => ['nullable', 'string', 'max:255'],
            'exemption_181' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $company = Entity::find($this->input('company_entity_id'));
            $person = Entity::find($this->input('person_entity_id'));

            if ($company && $company->type === EntityType::Person) {
                $v->errors()->add('company_entity_id', 'Die gewählte Entity ist kein Unternehmen.');
            }
            if ($person && $person->type !== EntityType::Person) {
                $v->errors()->add('person_entity_id', 'Die gewählte Entity ist keine Privatperson.');
            }

            if ($company && $person && ! $v->errors()->any()) {
                $exists = OrganizationRole::query()
                    ->where('company_entity_id', $company->id)
                    ->where('person_entity_id', $person->id)
                    ->where('role', $this->input('role'))
                    ->where('is_active', true)
                    ->exists();
                if ($exists) {
                    $v->errors()->add('role', 'Für diese Person besteht bereits eine aktive Organstellung dieser Art in diesem Unternehmen. Bitte zuerst die bestehende Stellung beenden.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'company_entity_id' => 'Unternehmen',
            'person_entity_id' => 'Person',
            'role' => 'Rolle',
            'started_on' => 'Beginn',
            'sole_representation' => 'Einzelvertretung',
            'representation_rule' => 'Vertretungsregel',
            'exemption_181' => 'Befreiung von § 181 BGB',
            'note' => 'Notiz',
        ];
    }
}
