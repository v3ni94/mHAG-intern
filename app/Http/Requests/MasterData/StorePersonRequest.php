<?php

namespace App\Http\Requests\MasterData;

class StorePersonRequest extends MasterDataFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('persons.create');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull([
            'salutation', 'title', 'middle_names', 'birth_name', 'date_of_birth',
            'place_of_birth', 'nationality', 'gender', 'marital_status', 'tags', 'notes',
            'address_street', 'address_house_number', 'address_addition', 'address_postal_code', 'address_city', 'address_country',
        ]);
    }

    public function rules(): array
    {
        return [
            'salutation' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_names' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
            // Anschrift beim Anlegen (optional, Masterprompt 6 und 7)
            'address_type' => ['nullable', 'string', 'in:main,secondary,business,correspondence,historical'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_house_number' => ['nullable', 'string', 'max:50'],
            'address_addition' => ['nullable', 'string', 'max:255'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'address_city' => ['nullable', 'string', 'max:255'],
            'address_country' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'salutation' => 'Anrede',
            'title' => 'Titel',
            'first_name' => 'Vorname',
            'middle_names' => 'Weitere Vornamen',
            'last_name' => 'Nachname',
            'birth_name' => 'Geburtsname',
            'date_of_birth' => 'Geburtsdatum',
            'place_of_birth' => 'Geburtsort',
            'nationality' => 'Staatsangehörigkeit',
            'gender' => 'Geschlecht',
            'marital_status' => 'Familienstand',
            'tags' => 'Tags',
            'notes' => 'Interne Notizen',
            'address_type' => 'Adressart',
            'address_street' => 'Straße',
            'address_house_number' => 'Hausnummer',
            'address_addition' => 'Adresszusatz',
            'address_postal_code' => 'PLZ',
            'address_city' => 'Ort',
            'address_country' => 'Land',
        ];
    }

    /** Personenfelder für die persons-Tabelle. */
    public function personData(): array
    {
        return $this->safe()->only([
            'salutation', 'title', 'first_name', 'middle_names', 'last_name',
            'birth_name', 'date_of_birth', 'place_of_birth', 'nationality',
            'gender', 'marital_status',
        ]);
    }

    /** Tags-Eingabe (Kommagetrennt) als Array. */
    public function tagsArray(): ?array
    {
        $raw = $this->validated('tags');
        if ($raw === null) {
            return null;
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== ''));

        return $tags === [] ? null : $tags;
    }
}
