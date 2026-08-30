<?php

namespace App\Http\Requests\Holding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Basis für alle Holding-FormRequests: deutsche Fehlermeldungen.
 * Berechtigungen werden über die Routen-Middleware (permission:...) geprüft.
 */
abstract class HoldingFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Das Feld :attribute ist erforderlich.',
            'required' => 'Das Feld :attribute ist erforderlich.',
            '*.date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
            'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
            '*.integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
            'integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
            '*.numeric' => 'Das Feld :attribute muss eine Zahl sein.',
            'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
            '*.min' => 'Das Feld :attribute ist zu klein.',
            'min' => 'Das Feld :attribute ist zu klein.',
            '*.max' => 'Das Feld :attribute ist zu groß bzw. zu lang.',
            'max' => 'Das Feld :attribute ist zu groß bzw. zu lang.',
            '*.exists' => 'Der ausgewählte Wert für :attribute ist ungültig.',
            'exists' => 'Der ausgewählte Wert für :attribute ist ungültig.',
            '*.unique' => 'Der Wert für :attribute ist bereits vergeben.',
            'unique' => 'Der Wert für :attribute ist bereits vergeben.',
            '*.in' => 'Der ausgewählte Wert für :attribute ist ungültig.',
            'in' => 'Der ausgewählte Wert für :attribute ist ungültig.',
            '*.email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
            'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
            '*.mimes' => 'Das Feld :attribute muss eine Datei des Typs :values sein.',
            'mimes' => 'Das Feld :attribute muss eine Datei des Typs :values sein.',
            '*.file' => 'Das Feld :attribute muss eine Datei sein.',
            'file' => 'Das Feld :attribute muss eine Datei sein.',
            '*.string' => 'Das Feld :attribute muss Text sein.',
            'string' => 'Das Feld :attribute muss Text sein.',
            '*.boolean' => 'Das Feld :attribute muss ja oder nein sein.',
            'boolean' => 'Das Feld :attribute muss ja oder nein sein.',
            '*.array' => 'Das Feld :attribute hat ein ungültiges Format.',
            'array' => 'Das Feld :attribute hat ein ungültiges Format.',
            '*.after_or_equal' => 'Das Feld :attribute muss zeitlich nach :date oder gleich sein.',
            'after_or_equal' => 'Das Feld :attribute muss zeitlich nach :date oder gleich sein.',
        ];
    }
}
