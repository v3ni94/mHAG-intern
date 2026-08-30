<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FAQ-Verwaltung (Abschnitt 114 Masterprompt): Frage, Antwort, Kategorie,
 * Sichtbarkeit je Rolle, Sortierung, aktiv/inaktiv.
 */
class FaqEntryRequest extends FormRequest
{
    public const VISIBILITIES = ['all', 'internal', 'admin', 'supervisory_board', 'lender', 'borrower'];

    public function authorize(): bool
    {
        return $this->user()?->can('admin.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:255'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:10000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'visibility' => ['required', Rule::in(self::VISIBILITIES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'Kategorie',
            'question' => 'Frage',
            'answer' => 'Antwort',
            'sort' => 'Sortierung',
            'visibility' => 'Sichtbarkeit',
            'is_active' => 'Aktiv',
        ];
    }
}
