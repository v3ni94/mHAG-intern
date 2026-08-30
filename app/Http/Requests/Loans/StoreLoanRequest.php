<?php

namespace App\Http\Requests\Loans;

use App\Enums\InterestDueDayMode;
use App\Enums\InterestFrequency;
use App\Enums\InterestMethod;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentModel;
use App\Enums\RiskRating;
use App\Models\Entity;
use App\Services\Loans\DefaultInterestService;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanRequest extends LoansFormRequest
{
    protected array $moneyFields = ['principal_amount', 'credit_limit'];

    protected array $percentFields = ['interest_rate', 'default_interest_rate'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('loans.create');
    }

    /**
     * Auszahlungszeilen (Abschnitt 31): leere Zeilen des wiederholbaren
     * Formularblocks entfernen und die Betraege aus deutscher Schreibweise
     * ("50.000,00") in Dezimalstrings wandeln.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $rows = $this->input('disbursements');
        if (! is_array($rows)) {
            return;
        }

        $cleaned = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = trim((string) ($row['amount'] ?? ''));
            $date = trim((string) ($row['date'] ?? ''));
            if ($amount === '' && $date === '') {
                continue; // unausgefuellte Zeile: ignorieren
            }
            $row['amount'] = $amount === '' ? null : (Money::parse($amount) ?? $amount);
            $row['date'] = $date === '' ? null : $date;
            $row['status'] = trim((string) ($row['status'] ?? 'planned'));
            $cleaned[] = $row;
        }

        $this->merge(['disbursements' => $cleaned]);
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
            'interest_due_month' => ['nullable', 'integer', 'between:1,12'],
            'repayment_model' => ['required', Rule::enum(RepaymentModel::class)],
            'interest_rate' => ['required', 'numeric', 'gte:0', 'max:100'],
            // Verzugszinsen (Abschnitt 44): ausschliesslich fachliche Vorgaben,
            // keine gesetzlichen Vorbelegungen.
            'default_interest_enabled' => ['nullable', 'boolean'],
            'default_interest_rate' => ['nullable', 'numeric', 'gte:0', 'max:100'],
            'default_interest_start' => ['nullable', 'date'],
            'default_interest_basis' => ['nullable', Rule::in(array_keys(DefaultInterestService::BASIS_LABELS))],
            'default_interest_method' => ['nullable', Rule::enum(InterestMethod::class)],
            'default_interest_mode' => ['nullable', Rule::in(array_keys(DefaultInterestService::MODE_LABELS))],
            'risk_rating' => ['nullable', Rule::enum(RiskRating::class)],
            'handler_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'project' => ['nullable', 'string', 'max:255'],
            'cost_center' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:65000'],
            // Auszahlungen (Abschnitt 31): beliebig viele Teilauszahlungen mit
            // Datum, Betrag und Status. Bestaetigte Zeilen erzeugen sofort die
            // Kapitalbuchung, damit der Zinsverlauf taggenau stimmt.
            'disbursements' => ['nullable', 'array', 'max:120'],
            'disbursements.*.date' => ['required', 'date'],
            'disbursements.*.amount' => ['required', 'numeric', 'gt:0'],
            'disbursements.*.status' => ['required', Rule::in(['planned', 'confirmed'])],
            'disbursements.*.origin' => ['nullable', Rule::enum(PaymentOrigin::class)],
            'disbursements.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Summe der Auszahlungen gegen den Darlehensrahmen pruefen
     * (Rahmen = credit_limit, sonst Darlehenssumme). Eine kleinere Summe
     * ist zulaessig (Teilauszahlung), eine groessere nicht.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = $this->input('disbursements');
            if (! is_array($rows) || $rows === []) {
                return;
            }

            $limit = Money::parse((string) $this->input('credit_limit'))
                ?? Money::parse((string) $this->input('principal_amount'));
            if ($limit === null || ! Money::isPositive($limit)) {
                return;
            }

            $sum = '0.00';
            foreach ($rows as $row) {
                $amount = is_array($row) ? ($row['amount'] ?? null) : null;
                if ($amount === null || $amount === '' || ! is_numeric($amount)) {
                    return; // Betragsfehler melden die Feldregeln
                }
                $sum = Money::add($sum, $amount);
            }

            if (Money::cmp($sum, $limit) > 0) {
                $validator->errors()->add('disbursements', sprintf(
                    'Die Summe der Auszahlungen (%s) übersteigt den Darlehensrahmen (%s). Bitte Beträge oder Rahmen korrigieren.',
                    Money::format($sum),
                    Money::format($limit),
                ));
            }
        });
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
            'interest_due_day_mode' => 'Fälligkeitstag der Zinsen',
            'interest_capitalization' => 'Zinskapitalisierung',
            'interest_capitalization_from' => 'Zuschreibung ab',
            'interest_due_day' => 'Fester Fälligkeitstag',
            'interest_due_month' => 'Fälligkeitsmonat',
            'repayment_model' => 'Tilgungsmodell',
            'interest_rate' => 'Zinssatz',
            'default_interest_enabled' => 'Verzugszinsen aktiv',
            'default_interest_rate' => 'Verzugszinssatz',
            'default_interest_start' => 'Verzugsbeginn',
            'default_interest_basis' => 'Berechnungsgrundlage der Verzugszinsen',
            'default_interest_method' => 'Zinsmethode der Verzugszinsen',
            'default_interest_mode' => 'Aktivierung der Verzugszinsen',
            'risk_rating' => 'Risiko-Einstufung',
            'handler_user_id' => 'Sachbearbeiter',
            'project' => 'Projekt',
            'cost_center' => 'Kostenstelle',
            'internal_notes' => 'Interne Notizen',
            'disbursements' => 'Auszahlungen',
            'disbursements.*.date' => 'Auszahlungsdatum',
            'disbursements.*.amount' => 'Auszahlungsbetrag',
            'disbursements.*.status' => 'Auszahlungsstatus',
            'disbursements.*.origin' => 'Herkunft der Auszahlung',
            'disbursements.*.reference' => 'Auszahlungsreferenz',
        ];
    }
}
