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
use Illuminate\Validation\Validator;

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

    /**
     * Bezug und Zuweisung gegen die Sichtbarkeit prüfen.
     *
     * Zuvor war "remindable_id" nur als Zahl geprüft. Die Wiedervorlagenliste
     * zeigt zum Bezug aber Bezeichnung, Darlehensnummer oder Titel des
     * verknüpften Objekts an (resources/views/reminders/index.blade.php).
     * Damit ließ sich der Datenscope umgehen: Eine externe Rolle konnte eine
     * Wiedervorlage auf ein beliebiges Darlehen, einen Vertrag, einen
     * Beschluss oder eine Aktienbewegung setzen und dessen Bezeichnung
     * auslesen, auch bei ausgeschlossenen Gesellschaften und auch bei
     * Vorgängen, deren eigene Seite mit 403 gesperrt ist.
     *
     * Ebenso die Zuweisung: Eine externe Rolle weist nur sich selbst zu. Das
     * vollständige Benutzerverzeichnis ist für sie weder nötig noch zulässig.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $benutzer = $this->user();
            if ($benutzer === null) {
                return;
            }

            if (! $benutzer->isInternal() && (int) $this->input('assigned_to') !== (int) $benutzer->id) {
                $v->errors()->add('assigned_to',
                    'Eine Wiedervorlage kann nur dem eigenen Konto zugewiesen werden.');
            }

            $typ = (string) $this->input('remindable_type');
            $id = (int) $this->input('remindable_id');
            if ($typ === '' || $id <= 0 || ! array_key_exists($typ, self::REMINDABLE_TYPES)) {
                return;
            }

            if (! $this->bezugIstSichtbar($typ, $id)) {
                $v->errors()->add('remindable_id',
                    'Der gewählte Bezug ist nicht verfügbar. Bitte einen Vorgang wählen, auf den '
                    .'Sie Zugriff haben.');
            }
        });
    }

    /** Darf der Benutzer den angegebenen Vorgang sehen? */
    private function bezugIstSichtbar(string $typ, int $id): bool
    {
        $benutzer = $this->user();

        return match ($typ) {
            'entity' => Entity::query()->visibleTo($benutzer)->whereKey($id)->exists(),
            'loan' => Loan::query()->visibleTo($benutzer)->whereKey($id)->exists(),
            'contract' => Contract::query()->visibleTo($benutzer)->whereKey($id)->exists(),
            // Berechtigung UND Sichtbarkeit: die Berechtigung entscheidet, ob
            // der Bereich ueberhaupt offensteht, der Scope, welcher Vorgang
            // darin sichtbar ist.
            'resolution' => (bool) $benutzer?->can('resolutions.view')
                && Resolution::query()->visibleTo($benutzer)->whereKey($id)->exists(),
            'share_transaction' => (bool) $benutzer?->can('shares.view')
                && ShareTransaction::query()->visibleTo($benutzer)->whereKey($id)->exists(),
            'investment' => (bool) $benutzer?->can('shares.view')
                && Investment::query()->visibleTo($benutzer)->whereKey($id)->exists(),
            default => false,
        };
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
