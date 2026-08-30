<?php

namespace App\Http\Requests\MasterData;

class StoreCompanyRequest extends MasterDataFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('companies.create');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull([
            'short_name', 'legal_form', 'founded_on', 'commercial_register', 'register_number',
            'register_court', 'tax_number', 'vat_id', 'business_id', 'website', 'email',
            'phone', 'fax', 'industry', 'tags', 'notes',
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'legal_form' => ['nullable', 'string', 'max:100'],
            'founded_on' => ['nullable', 'date', 'before_or_equal:today'],
            'commercial_register' => ['nullable', 'string', 'max:100'],
            'register_number' => ['nullable', 'string', 'max:100'],
            'register_court' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'business_id' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'fax' => ['nullable', 'string', 'max:100'],
            'industry' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Firmenname',
            'short_name' => 'Kurzname',
            'legal_form' => 'Rechtsform',
            'founded_on' => 'Gründungsdatum',
            'commercial_register' => 'Handelsregister',
            'register_number' => 'Registernummer',
            'register_court' => 'Registergericht',
            'tax_number' => 'Steuernummer',
            'vat_id' => 'Umsatzsteuer-ID',
            'business_id' => 'Wirtschafts-ID',
            'website' => 'Website',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'fax' => 'Fax',
            'industry' => 'Branche',
            'tags' => 'Tags',
            'notes' => 'Interne Notizen',
        ];
    }

    /** Unternehmensfelder für die companies-Tabelle. */
    public function companyData(): array
    {
        return $this->safe()->only([
            'name', 'short_name', 'legal_form', 'founded_on', 'commercial_register',
            'register_number', 'register_court', 'tax_number', 'vat_id', 'business_id',
            'website', 'email', 'phone', 'fax', 'industry',
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
