<?php

namespace App\Http\Requests\Loans;

use App\Models\Entity;
use Illuminate\Validation\Rule;

/**
 * Bürgschaften (Abschnitt 67 Masterprompt): mehrere Bürgen je Darlehen.
 */
class StoreGuaranteeRequest extends LoansFormRequest
{
    protected array $moneyFields = ['max_amount'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        $visibleEntityIds = Entity::visibleTo($this->user())->pluck('id')->all();

        return [
            'guarantor_entity_id' => ['required', 'integer', Rule::in($visibleEntityIds)],
            'guarantee_type' => ['nullable', 'string', 'max:255'],
            'max_amount' => ['nullable', 'numeric', 'gt:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['required', Rule::in(['active', 'released', 'expired'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'guarantor_entity_id' => 'Bürge',
            'guarantee_type' => 'Bürgschaftsart',
            'max_amount' => 'Höchstbetrag',
            'valid_from' => 'Beginn',
            'valid_until' => 'Ende',
            'status' => 'Status',
        ];
    }
}
