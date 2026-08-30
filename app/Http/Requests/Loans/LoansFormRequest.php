<?php

namespace App\Http\Requests\Loans;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gemeinsame Basis der Darlehens-FormRequests: deutsche Fehlermeldungen,
 * Umwandlung deutscher Geld- und Prozenteingaben ("1.234,56" bzw. "6,5")
 * in Dezimalstrings vor der Validierung.
 */
abstract class LoansFormRequest extends FormRequest
{
    /** Felder, die als Geldbetrag geparst werden (Money::parse, 2 Nachkommastellen). */
    protected array $moneyFields = [];

    /** Felder, die als Prozentsatz geparst werden (bis 6 Nachkommastellen). */
    protected array $percentFields = [];

    protected function prepareForValidation(): void
    {
        $converted = [];
        foreach ($this->moneyFields as $field) {
            if ($this->filled($field)) {
                $parsed = Money::parse((string) $this->input($field));
                $converted[$field] = $parsed ?? $this->input($field);
            }
        }
        foreach ($this->percentFields as $field) {
            if ($this->filled($field)) {
                $parsed = static::parsePercent((string) $this->input($field));
                $converted[$field] = $parsed ?? $this->input($field);
            }
        }
        if ($converted !== []) {
            $this->merge($converted);
        }
    }

    /**
     * Deutsche Prozenteingabe ("6,5" oder "3,125") in Dezimalstring wandeln,
     * ohne auf 2 Nachkommastellen zu runden (Zinssätze: DECIMAL(9,6)).
     */
    public static function parsePercent(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        $s = trim(str_replace([' ', "\u{a0}", '%'], '', $input));
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }

        return $s;
    }

    public function messages(): array
    {
        return [
            'required' => 'Das Feld ":attribute" ist erforderlich.',
            'required_if' => 'Das Feld ":attribute" ist erforderlich.',
            'required_with' => 'Das Feld ":attribute" ist erforderlich.',
            'date' => 'Das Feld ":attribute" muss ein gültiges Datum sein.',
            'numeric' => 'Das Feld ":attribute" muss eine Zahl sein (Format z. B. 1.234,56).',
            'integer' => 'Das Feld ":attribute" muss eine ganze Zahl sein.',
            'min' => 'Das Feld ":attribute" ist zu klein.',
            'max' => 'Das Feld ":attribute" ist zu groß bzw. zu lang.',
            'in' => 'Der gewählte Wert für ":attribute" ist ungültig.',
            'exists' => 'Der gewählte Wert für ":attribute" ist ungültig.',
            'different' => 'Die Felder ":attribute" und ":other" dürfen nicht identisch sein.',
            'after_or_equal' => 'Das Feld ":attribute" muss nach ":date" oder gleich ":date" liegen.',
            'gt' => 'Das Feld ":attribute" muss größer als :value sein.',
            'gte' => 'Das Feld ":attribute" muss größer oder gleich :value sein.',
            'boolean' => 'Das Feld ":attribute" muss ja oder nein sein.',
            'string' => 'Das Feld ":attribute" muss Text sein.',
            'array' => 'Das Feld ":attribute" hat ein ungültiges Format.',
        ];
    }
}
