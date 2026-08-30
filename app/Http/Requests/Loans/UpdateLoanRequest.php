<?php

namespace App\Http\Requests\Loans;

use App\Enums\InterestDueDayMode;
use App\Enums\InterestFrequency;
use App\Enums\InterestMethod;
use App\Enums\RepaymentModel;
use App\Enums\RiskRating;
use App\Models\Entity;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends LoansFormRequest
{
    protected array $moneyFields = ['principal_amount', 'credit_limit'];

    protected array $percentFields = ['default_interest_rate'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.update');
    }

    public function rules(): array
    {
        $visibleEntityIds = Entity::visibleTo($this->user())->pluck('id')->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'lender_entity_id' => ['required', 'integer', Rule::in($visibleEntityIds)],
            'borrower_entity_id' => ['required', 'integer', 'different:lender_entity_id', Rule::in($visibleEntityIds)],
            'loan_type_id' => ['nullable', 'integer', Rule::exists('loan_types', 'id')],
            'contract_basis' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
            'effective_from' => ['required', 'date'],
            'disbursement_date' => ['nullable', 'date'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'due_date' => ['nullable', 'date'],
            'notice_period' => ['nullable', 'string', 'max:255'],
            'contract_end' => ['nullable', 'date'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'credit_limit' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'interest_frequency' => ['required', Rule::enum(InterestFrequency::class)],
            'interest_due_day_mode' => ['nullable', Rule::enum(InterestDueDayMode::class)],
            'interest_capitalization' => ['nullable', 'boolean'],
            'interest_capitalization_from' => ['nullable', 'date'],
            'interest_due_day' => [
                'nullable',
                'integer',
                'between:'.InterestDueDayMode::FIXED_DAY_MIN.','.InterestDueDayMode::FIXED_DAY_MAX,
                Rule::requiredIf(fn () => $this->input('interest_due_day_mode') === InterestDueDayMode::FixedDay->value),
            ],
            'repayment_model' => ['required', Rule::enum(RepaymentModel::class)],
            // Verzugszinsen (Abschnitt 44): nur fachliche Vorgaben, keine Vorbelegung
            'default_interest_enabled' => ['nullable', 'boolean'],
            'default_interest_rate' => ['nullable', 'numeric', 'gte:0', 'max:100'],
            'default_interest_start' => ['nullable', 'date'],
            'default_interest_basis' => ['nullable', Rule::in(array_keys(\App\Services\Loans\DefaultInterestService::BASIS_LABELS))],
            'default_interest_method' => ['nullable', Rule::enum(InterestMethod::class)],
            'default_interest_mode' => ['nullable', Rule::in(array_keys(\App\Services\Loans\DefaultInterestService::MODE_LABELS))],
            'risk_rating' => ['nullable', Rule::enum(RiskRating::class)],
            'handler_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'project' => ['nullable', 'string', 'max:255'],
            'cost_center' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:65000'],
        ];
    }

    public function attributes(): array
    {
        return (new StoreLoanRequest)->attributes();
    }
}
