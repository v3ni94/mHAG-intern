<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Validation\Validator;

class BankAccountRequest extends EntitySubResourceRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyToNull(['bic', 'bank_name', 'note']);
        $this->normalizeCheckboxes(['is_primary', 'is_active']);

        if ($this->filled('iban')) {
            $this->merge(['iban' => strtoupper(str_replace([' ', "\u{a0}"], '', (string) $this->input('iban')))]);
        }
        if ($this->filled('bic')) {
            $this->merge(['bic' => strtoupper(str_replace(' ', '', (string) $this->input('bic')))]);
        }
    }

    public function rules(): array
    {
        return [
            'account_holder' => ['required', 'string', 'max:255'],
            'iban' => ['required', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/'],
            'bic' => ['nullable', 'string', 'max:11', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $iban = (string) $this->input('iban');
            if ($iban !== '' && ! $v->errors()->has('iban') && ! self::ibanChecksumValid($iban)) {
                $v->errors()->add('iban', 'Die IBAN-Prüfsumme ist ungültig. Bitte prüfen Sie die Eingabe.');
            }
        });
    }

    /** Mod-97-Prüfung nach ISO 13616 (ohne Gleitkommaarithmetik). */
    public static function ibanChecksumValid(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        return bcmod($numeric, '97') === '1';
    }

    public function attributes(): array
    {
        return [
            'account_holder' => 'Kontoinhaber',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'bank_name' => 'Bank',
            'currency' => 'Währung',
            'is_primary' => 'Hauptkonto',
            'is_active' => 'Aktiv',
            'note' => 'Notiz',
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'iban.regex' => 'Die IBAN hat kein gültiges Format (z. B. DE89 3704 0044 0532 0130 00).',
            'bic.regex' => 'Die BIC hat kein gültiges Format (8 oder 11 Stellen).',
        ]);
    }
}
