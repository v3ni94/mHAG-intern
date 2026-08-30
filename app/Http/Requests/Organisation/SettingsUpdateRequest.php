<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Systemeinstellungen: 2FA-Pflichtrollen, Verrechnungsreihenfolge (Abschnitt 47)
 * und Upload-Limits.
 */
class SettingsUpdateRequest extends FormRequest
{
    public const ALLOCATION_BUCKETS = ['costs', 'fees', 'default_interest', 'interest', 'principal'];

    public function authorize(): bool
    {
        return $this->user()?->can('admin.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'two_factor_required_roles' => ['nullable', 'array'],
            'two_factor_required_roles.*' => ['string', Rule::exists('roles', 'name')],
            'allocation_order' => ['required', 'array', 'size:'.count(self::ALLOCATION_BUCKETS)],
            'allocation_order.*' => ['required', Rule::in(self::ALLOCATION_BUCKETS), 'distinct'],
            'max_size_kb' => ['required', 'integer', 'min:128', 'max:1048576'],
        ];
    }

    public function attributes(): array
    {
        return [
            'two_factor_required_roles' => '2FA-Pflichtrollen',
            'allocation_order' => 'Verrechnungsreihenfolge',
            'max_size_kb' => 'Maximale Dateigröße (KB)',
        ];
    }

    public function messages(): array
    {
        return [
            'allocation_order.size' => 'Die Verrechnungsreihenfolge muss alle fünf Positionen enthalten.',
            'allocation_order.*.distinct' => 'Jede Position darf in der Verrechnungsreihenfolge nur einmal vorkommen.',
        ];
    }
}
