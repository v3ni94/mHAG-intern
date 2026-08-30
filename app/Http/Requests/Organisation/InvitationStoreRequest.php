<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Benutzereinladung (Abschnitt 12 Masterprompt): E-Mail, optionale Entity,
 * Rollen und Datenbereich (entity_ids).
 */
class InvitationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.users') ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'entity_id' => ['nullable', 'integer', Rule::exists('entities', 'id')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', Rule::exists('entities', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'E-Mail-Adresse',
            'entity_id' => 'Zugeordnete Entität',
            'roles' => 'Rollen',
            'entity_ids' => 'Datenbereich',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Für diese E-Mail-Adresse existiert bereits ein Benutzerkonto.',
            'roles.required' => 'Mindestens eine Rolle muss ausgewählt werden.',
        ];
    }
}
