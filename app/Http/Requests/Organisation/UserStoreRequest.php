<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.users') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'entity_id' => ['nullable', 'integer', Rule::exists('entities', 'id')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'password' => 'Passwort',
            'entity_id' => 'Zugeordnete Entität',
            'roles' => 'Rollen',
            'is_active' => 'Aktiv',
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
