<?php

namespace App\Http\Requests\Loans;

use App\Enums\SecurityType;
use App\Models\Entity;
use Illuminate\Validation\Rule;

/**
 * Sicherheiten (Abschnitt 66 Masterprompt).
 */
class StoreSecurityRequest extends LoansFormRequest
{
    protected array $moneyFields = ['nominal_value', 'internal_value'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        $visibleEntityIds = Entity::visibleTo($this->user())->pluck('id')->all();

        return [
            'provider_entity_id' => ['nullable', 'integer', Rule::in($visibleEntityIds)],
            'type' => ['required', Rule::enum(SecurityType::class)],
            'nominal_value' => ['nullable', 'numeric', 'gt:0'],
            'internal_value' => ['nullable', 'numeric', 'gte:0'],
            'rank' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'released', 'expired'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'provider_entity_id' => 'Sicherungsgeber',
            'type' => 'Art',
            'nominal_value' => 'Nominalwert',
            'internal_value' => 'Interner Wert',
            'rank' => 'Rang',
            'valid_from' => 'Beginn',
            'valid_until' => 'Ende',
            'description' => 'Beschreibung',
            'status' => 'Status',
        ];
    }
}
