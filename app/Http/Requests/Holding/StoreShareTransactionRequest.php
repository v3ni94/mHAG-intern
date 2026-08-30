<?php

namespace App\Http\Requests\Holding;

use App\Enums\ShareTransactionType;
use App\Http\Requests\Concerns\ParstDeutscheBetraege;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Erfassung einer Aktienbewegung (Abschnitt 79). Beträge werden im deutschen
 * Format ("1.234,56") akzeptiert und vor der Validierung geparst.
 */
class StoreShareTransactionRequest extends HoldingFormRequest
{
    use ParstDeutscheBetraege;

    protected function prepareForValidation(): void
    {
        // Kurs je Aktie fuehrt vier Nachkommastellen (DECIMAL(18,4)). Mit der
        // Vorgabe von zwei Stellen wurde 12,3456 stillschweigend zu 12,34.
        $this->parstBetrag('price_per_share', 4);
        $this->parstBetrag('total_price', 2);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->betragsfehlerMelden($v));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ShareTransactionType::class)],
            'seller_shareholder_id' => ['nullable', 'integer', 'exists:shareholders,id', 'required_if:type,sale,transfer,gift,redemption,capital_decrease'],
            'buyer_shareholder_id' => ['nullable', 'integer', 'exists:shareholders,id', 'different:seller_shareholder_id', 'required_if:type,purchase,sale,transfer,gift,capital_increase'],
            'share_count' => ['required', 'integer', 'min:1'],
            'price_per_share' => ['nullable', 'numeric', 'min:0'],
            'total_price' => ['nullable', 'numeric', 'min:0'],
            'contract_date' => ['nullable', 'date'],
            'economic_transfer_date' => ['required', 'date'],
            'booking_date' => ['nullable', 'date'],
            'resolution_id' => ['nullable', 'integer', 'exists:resolutions,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Transaktionsart',
            'seller_shareholder_id' => 'Verkäufer bzw. abgebender Aktionär',
            'buyer_shareholder_id' => 'Käufer bzw. empfangender Aktionär',
            'share_count' => 'Anzahl Aktien',
            'price_per_share' => 'Kaufpreis je Aktie',
            'total_price' => 'Gesamtkaufpreis',
            'contract_date' => 'Vertragsdatum',
            'economic_transfer_date' => 'Wirtschaftlicher Übergang',
            'booking_date' => 'Buchungsdatum',
            'resolution_id' => 'Beschluss',
            'contract_id' => 'Vertrag',
            'note' => 'Notiz',
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'seller_shareholder_id.required_if' => 'Für diese Transaktionsart muss ein abgebender Aktionär angegeben werden.',
            'buyer_shareholder_id.required_if' => 'Für diese Transaktionsart muss ein empfangender Aktionär angegeben werden.',
            'buyer_shareholder_id.different' => 'Käufer und Verkäufer müssen unterschiedlich sein.',
            'share_count.min' => 'Die Anzahl der Aktien muss mindestens 1 betragen.',
        ]);
    }
}
