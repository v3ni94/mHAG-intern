<?php

namespace App\Http\Requests\Loans;

use App\Enums\InterestFrequency;
use App\Enums\InterestMethod;
use App\Enums\RepaymentModel;
use App\Enums\RiskRating;
use App\Models\Entity;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends LoansFormRequest
{
    protected array $moneyFields = ['principal_amount', 'credit_limit', 'disbursement_planned_amount'];

    protected array $percentFields = ['interest_rate', 'default_interest_rate'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.create');
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
            'repayment_model' => ['required', Rule::enum(RepaymentModel::class)],
            'interest_rate' => ['required', 'numeric', 'gte:0', 'max:100'],
            'default_interest_enabled' => ['nullable', 'boolean'],
            'default_interest_rate' => ['nullable', 'numeric', 'gte:0', 'max:100'],
            'risk_rating' => ['nullable', Rule::enum(RiskRating::class)],
            'handler_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'project' => ['nullable', 'string', 'max:255'],
            'cost_center' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:65000'],
            // Optional: Auszahlung direkt planen (Abschnitt 31)
            'plan_disbursement' => ['nullable', 'boolean'],
            'disbursement_planned_amount' => ['nullable', 'required_if:plan_disbursement,1', 'numeric', 'gt:0'],
            'disbursement_planned_date' => ['nullable', 'required_if:plan_disbursement,1', 'date'],
            'disbursement_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Bezeichnung',
            'lender_entity_id' => 'Darlehensgeber',
            'borrower_entity_id' => 'Darlehensnehmer',
            'loan_type_id' => 'Darlehensart',
            'contract_basis' => 'Vertragsgrundlage',
            'contract_date' => 'Vertragsdatum',
            'effective_from' => 'Wirkungsbeginn',
            'disbursement_date' => 'Auszahlungstag',
            'term_months' => 'Laufzeit (Monate)',
            'due_date' => 'Fälligkeit',
            'notice_period' => 'Kündigungsfrist',
            'contract_end' => 'Vertragsende',
            'principal_amount' => 'Darlehenssumme',
            'credit_limit' => 'Darlehensrahmen',
            'currency' => 'Währung',
            'interest_method' => 'Zinsmethode',
            'interest_frequency' => 'Zinsfälligkeit',
            'repayment_model' => 'Tilgungsmodell',
            'interest_rate' => 'Zinssatz',
            'default_interest_enabled' => 'Verzugszinsen aktiv',
            'default_interest_rate' => 'Verzugszinssatz',
            'risk_rating' => 'Risiko-Einstufung',
            'handler_user_id' => 'Sachbearbeiter',
            'project' => 'Projekt',
            'cost_center' => 'Kostenstelle',
            'internal_notes' => 'Interne Notizen',
            'plan_disbursement' => 'Auszahlung planen',
            'disbursement_planned_amount' => 'Geplanter Auszahlungsbetrag',
            'disbursement_planned_date' => 'Geplantes Auszahlungsdatum',
            'disbursement_reference' => 'Auszahlungsreferenz',
        ];
    }
}
