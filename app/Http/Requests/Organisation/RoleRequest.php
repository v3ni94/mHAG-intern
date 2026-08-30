<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rollenverwaltung: Name (bei Standardrollen unveränderlich) und
 * Berechtigungs-Checkboxen.
 */
class RoleRequest extends FormRequest
{
    /** Standardrollen laut Seeder; Namen sind nicht änderbar, Rollen nicht löschbar. */
    public const SYSTEM_ROLES = [
        'Administrator', 'Vorstand', 'Aufsichtsratsvorsitzender', 'Aufsichtsratsmitglied',
        'Aktionär', 'Darlehensgeber', 'Darlehensnehmer', 'Buchhaltung', 'Sachbearbeiter',
        'Mitarbeiter', 'Nur Lesen',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('admin.roles') ?? false;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Rollenname', 'permissions' => 'Berechtigungen'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Eine Rolle mit diesem Namen existiert bereits.'];
    }
}
