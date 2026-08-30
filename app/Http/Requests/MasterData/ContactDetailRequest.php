<?php

namespace App\Http\Requests\MasterData;

class ContactDetailRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull(['label']);
        $this->normalizeCheckboxes(['is_primary']);
    }

    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'in:email,email_alt,phone,mobile,fax,other'],
            'value' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ];

        if (in_array($this->input('type'), ['email', 'email_alt'], true)) {
            $rules['value'][] = 'email';
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'type' => 'Kontaktart',
            'value' => 'Wert',
            'label' => 'Bezeichnung',
            'is_primary' => 'Hauptkontakt',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            'email' => 'E-Mail',
            'email_alt' => 'Alternative E-Mail',
            'phone' => 'Telefon',
            'mobile' => 'Mobiltelefon',
            'fax' => 'Fax',
            'other' => 'Sonstiger Kontakt',
        ];
    }
}
