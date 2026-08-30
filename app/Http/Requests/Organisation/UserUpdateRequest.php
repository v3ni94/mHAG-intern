<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.users') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->numbers()],
            'entity_id' => ['nullable', 'integer', Rule::exists('entities', 'id')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'is_active' => ['nullable', 'boolean'],
            // Datenbereich (user_entity_assignments)
            'assignments' => ['nullable', 'array'],
            'assignments.*.entity_id' => ['required', 'integer', Rule::exists('entities', 'id')],
            'assignments.*.context' => ['required', Rule::in(['self', 'company', 'supervisory_board'])],
            'assignments.*.label' => ['nullable', 'string', 'max:255'],
            'assignments.*.is_default' => ['nullable', 'boolean'],
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
            'assignments' => 'Datenbereich',
        ];
    }
}
