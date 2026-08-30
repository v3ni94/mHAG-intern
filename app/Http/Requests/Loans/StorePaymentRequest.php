<?php

namespace App\Http\Requests\Loans;

use App\Enums\PaymentOrigin;
use App\Models\Entity;
use App\Models\Loan;
use App\Support\Money;
use Illuminate\Validation\Rule;

/**
 * Zahlungseingang erfassen (Abschnitt 46 Masterprompt), optional mit
 * manueller Aufteilung auf Kosten/Gebühren/Verzugszinsen/Zinsen/Kapital.
 */
class StorePaymentRequest extends LoansFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('payments.record');
    }

    protected function prepareForValidation(): void
    {
        // Verschachtelte Aufteilungsbeträge einzeln parsen
        $alloc = $this->input('alloc', []);
        if (is_array($alloc)) {
            foreach ($alloc as $bucket => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $alloc[$bucket] = Money::parse($value) ?? $value;
                } else {
                    unset($alloc[$bucket]);
                }
            }
            $this->merge(['alloc' => $alloc]);
        }
        if ($this->filled('amount')) {
            $this->merge(['amount' => Money::parse((string) $this->input('amount')) ?? $this->input('amount')]);
        }
    }

    public function rules(): array
    {
        $visibleLoanIds = Loan::visibleTo($this->user())->pluck('id')->all();
        $visibleEntityIds = Entity::visibleTo($this->user())->pluck('id')->all();

        return [
            'loan_id' => ['required', 'integer', Rule::in($visibleLoanIds)],
            'payer_entity_id' => ['nullable', 'integer', Rule::in($visibleEntityIds)],
            'payee_entity_id' => ['nullable', 'integer', Rule::in($visibleEntityIds)],
            'payment_date' => ['required', 'date'],
            'value_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'direction' => ['required', Rule::in(['incoming', 'outgoing'])],
            'purpose' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'origin' => ['required', Rule::in([
                PaymentOrigin::ManualEntered->value,
                PaymentOrigin::ManualConfirmed->value,
                PaymentOrigin::BankImport->value,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
            'allocate_manually' => ['nullable', 'boolean'],
            'alloc' => ['nullable', 'array'],
            'alloc.costs' => ['nullable', 'numeric', 'gte:0'],
            'alloc.fees' => ['nullable', 'numeric', 'gte:0'],
            'alloc.default_interest' => ['nullable', 'numeric', 'gte:0'],
            'alloc.interest' => ['nullable', 'numeric', 'gte:0'],
            'alloc.principal' => ['nullable', 'numeric', 'gte:0'],
            'alloc.other' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('allocate_manually')) {
                return;
            }
            $alloc = array_filter((array) $this->input('alloc', []));
            if ($alloc === []) {
                $validator->errors()->add('alloc', 'Bei manueller Aufteilung muss mindestens ein Teilbetrag angegeben werden.');

                return;
            }
            $sum = Money::sum($alloc);
            if (Money::cmp($sum, (string) $this->input('amount')) !== 0) {
                $validator->errors()->add('alloc', 'Die Summe der Aufteilung ('.format_money($sum).') muss dem Zahlungsbetrag entsprechen.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'loan_id' => 'Darlehen',
            'payer_entity_id' => 'Zahler',
            'payee_entity_id' => 'Empfänger',
            'payment_date' => 'Zahlungsdatum',
            'value_date' => 'Valutadatum',
            'amount' => 'Betrag',
            'direction' => 'Richtung',
            'purpose' => 'Verwendungszweck',
            'reference' => 'Referenz',
            'origin' => 'Herkunft',
            'note' => 'Notiz',
            'alloc.costs' => 'Aufteilung Kosten',
            'alloc.fees' => 'Aufteilung Gebühren',
            'alloc.default_interest' => 'Aufteilung Verzugszinsen',
            'alloc.interest' => 'Aufteilung Zinsen',
            'alloc.principal' => 'Aufteilung Kapital',
            'alloc.other' => 'Aufteilung Sonstige',
        ];
    }
}
