<?php

namespace App\Http\Requests\Loans;

use App\Http\Requests\Concerns\ParstDeutscheBetraege;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Gemeinsame Basis der Darlehens-FormRequests: deutsche Fehlermeldungen,
 * Umwandlung deutscher Geld- und Prozenteingaben ("1.234,56" bzw. "6,5")
 * in Dezimalstrings vor der Validierung.
 */
abstract class LoansFormRequest extends FormRequest
{
    use ParstDeutscheBetraege;

    /** Felder, die als Geldbetrag geparst werden (Money::parse, 2 Nachkommastellen). */
    protected array $moneyFields = [];

    /** Felder, die als Prozentsatz geparst werden (bis 6 Nachkommastellen). */
    protected array $percentFields = [];

    protected function prepareForValidation(): void
    {
        /*
         * Der Rohwert bleibt bei nicht deutbarer Eingabe stehen und wird
         * ausdruecklich beanstandet. Frueher genuegte die Regel "numeric",
         * die aber eine Zahl mit zu vielen Nachkommastellen durchlaesst;
         * die Datenbank kuerzte dann stillschweigend.
         */
        $this->parstBetraege($this->moneyFields, Money::SCALE);
        $this->parstProzente($this->percentFields, 6);
    }

    /**
     * Beanstandungen aus der Betragsumwandlung melden.
     *
     * Unterklassen mit eigenem withValidator() muessen
     * $this->betragsfehlerMelden($validator) selbst aufrufen; sonst
     * ueberschreiben sie diese Meldung.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->betragsfehlerMelden($v));
    }

    /**
     * Deutsche Prozenteingabe ("6,5" oder "3,125") in Dezimalstring wandeln,
     * ohne auf 2 Nachkommastellen zu runden (Zinssätze: DECIMAL(9,6)).
     */
    public static function parsePercent(?string $input): ?string
    {
        return Money::parsePercent($input, 6);
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
