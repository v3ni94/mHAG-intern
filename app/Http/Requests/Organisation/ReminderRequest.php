<?php

namespace App\Http\Requests\Organisation;

use App\Models\Contract;
use App\Models\Entity;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Resolution;
use App\Models\ShareTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wiedervorlagen (Abschnitt 73 Masterprompt): Titel, Beschreibung, Datum,
 * optionale Uhrzeit, Zuweisung, Priorität und optionaler Bezug.
 */
class ReminderRequest extends FormRequest
{
    /** Erlaubte Bezugsobjekte (remindable morph). */
    public const REMINDABLE_TYPES = [
        'entity' => Entity::class,
        'loan' => Loan::class,
        'contract' => Contract::class,
        'resolution' => Resolution::class,
        'share_transaction' => ShareTransaction::class,
        'investment' => Investment::class,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'remindable_type' => ['nullable', Rule::in(array_keys(self::REMINDABLE_TYPES))],
            'remindable_id' => ['nullable', 'required_with:remindable_type', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'description' => 'Beschreibung',
            'due_date' => 'Fälligkeitsdatum',
            'due_time' => 'Uhrzeit',
            'assigned_to' => 'Zugewiesener Benutzer',
            'priority' => 'Priorität',
            'remindable_type' => 'Bezugstyp',
            'remindable_id' => 'Bezugsobjekt',
        ];
    }

    public function messages(): array
    {
        return [
            'due_time.date_format' => 'Die Uhrzeit muss im Format HH:MM angegeben werden.',
        ];
    }

    /** Aufgelöste Morph-Klasse oder null. */
    public function remindableClass(): ?string
    {
        $type = $this->input('remindable_type');

        return $type ? (self::REMINDABLE_TYPES[$type] ?? null) : null;
    }
}
