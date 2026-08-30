<?php

namespace App\Http\Requests\Loans;

use App\Enums\RepaymentItemStatus;
use Illuminate\Validation\Rule;

/**
 * Soll/Ist-Erfassung je Zahlungsplan-Position (Abschnitte 26-28 Masterprompt):
 * IST-Betrag, Status (bestätigt/teilweise/nicht bezahlt/verspätet), Datum,
 * Kommentar. Optional SOLL-Änderung (setzt manually_adjusted).
 */
class UpdateScheduleItemRequest extends LoansFormRequest
{
    protected array $moneyFields = ['actual_amount', 'planned_amount'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('payments.record');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                RepaymentItemStatus::Confirmed->value,
                RepaymentItemStatus::Partial->value,
                RepaymentItemStatus::Missed->value,
                RepaymentItemStatus::Late->value,
                RepaymentItemStatus::Waived->value,
            ])],
            // Bei "nicht bezahlt" und "erlassen" ist der IST-Betrag 0, sonst Pflicht
            'actual_amount' => [
                Rule::requiredIf(fn () => ! in_array($this->input('status'), [
                    RepaymentItemStatus::Missed->value,
                    RepaymentItemStatus::Waived->value,
                ], true)),
                'nullable', 'numeric', 'gte:0',
            ],
            'actual_date' => ['nullable', 'date'],
            'value_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:2000'],
            // Optionale SOLL-Änderung (nur dann manually_adjusted = true)
            'planned_amount' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'Status',
            'actual_amount' => 'IST-Betrag',
            'actual_date' => 'Zahlungsdatum',
            'value_date' => 'Valutadatum',
            'comment' => 'Kommentar',
            'planned_amount' => 'SOLL-Betrag',
        ];
    }
}
