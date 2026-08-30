<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Basis aller Stammdaten-FormRequests: deutsche Standard-Fehlermeldungen
 * (regelbasierte Fallbacks) und gemeinsame Hilfsfunktionen.
 */
abstract class MasterDataFormRequest extends FormRequest
{
    public function messages(): array
    {
        return [
            'required' => 'Das Feld :attribute ist erforderlich.',
            'string' => 'Das Feld :attribute muss ein Text sein.',
            'max' => 'Das Feld :attribute darf höchstens :max Zeichen lang sein.',
            'min' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
            'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
            'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
            'url' => 'Das Feld :attribute muss eine gültige Internetadresse sein.',
            'in' => 'Der gewählte Wert für :attribute ist ungültig.',
            'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
            'boolean' => 'Das Feld :attribute muss Ja oder Nein sein.',
            'integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
            'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
            'regex' => 'Das Format von :attribute ist ungültig.',
            'after_or_equal' => 'Das Feld :attribute muss am oder nach dem :date liegen.',
            'before_or_equal' => 'Das Feld :attribute muss am oder vor dem :date liegen.',
            'different' => 'Die Felder :attribute und :other dürfen nicht identisch sein.',
        ];
    }

    /**
     * Checkbox-Eingaben ("on"/fehlt) in echte bool-Werte wandeln.
     */
    protected function normalizeCheckboxes(array $fields): void
    {
        $merge = [];
        foreach ($fields as $field) {
            $merge[$field] = $this->boolean($field);
        }
        $this->merge($merge);
    }

    /**
     * Leere Strings in null wandeln (für nullable-Spalten).
     */
    protected function normalizeEmptyToNull(array $fields): void
    {
        $merge = [];
        foreach ($fields as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $merge[$field] = null;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Anschrift aus dem Anlegen-Formular, sofern eine erfasst wurde.
     *
     * Ohne Straße und Ort wird keine Adresse angelegt: Ein leerer Datensatz
     * hätte keinen fachlichen Wert und würde die Adressliste verwässern.
     *
     * @return array<string, mixed>|null
     */
    public function initialAddressData(string $standardTyp = 'main'): ?array
    {
        $strasse = $this->input('address_street');
        $ort = $this->input('address_city');

        if (blank($strasse) && blank($ort)) {
            return null;
        }

        return [
            'type' => $this->input('address_type') ?: $standardTyp,
            'street' => $strasse,
            'house_number' => $this->input('address_house_number'),
            'addition' => $this->input('address_addition'),
            'postal_code' => $this->input('address_postal_code'),
            'city' => $ort,
            'country' => $this->input('address_country') ?: 'Deutschland',
            'is_primary' => true,
        ];
    }
}
