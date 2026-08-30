<?php

namespace App\Http\Requests\MasterData;

class EndOrganizationRoleRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('ended_on')) {
            $this->merge(['ended_on' => now()->toDateString()]);
        }
        $this->normalizeEmptyToNull(['note']);
    }

    public function rules(): array
    {
        return [
            'ended_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ended_on' => 'Ende der Organstellung',
            'note' => 'Notiz',
        ];
    }
}
