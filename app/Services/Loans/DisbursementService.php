<?php

namespace App\Services\Loans;

use App\Enums\BookingType;
use App\Enums\DisbursementStatus;
use App\Enums\PaymentOrigin;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auszahlungen planen, bestaetigen, ausfallen lassen, stornieren
 * (Masterprompt Abschnitte 31-32).
 *
 * SOLL (planned_amount/planned_date) und IST (actual_amount/actual_date)
 * bleiben strikt getrennt. Bereits gebuchte eigene Transaktionen werden
 * NIE geloescht, sondern per Gegenbuchung storniert (Abschnitt 49).
 * Jede Aktion stoesst die Neuberechnung an.
 */
class DisbursementService
{
    /**
     * Sammelvorgang (mehrere Auszahlungen in einem Schritt): waehrend des
     * Sammelvorgangs wird die Neuberechnung zurueckgestellt und am Ende
     * genau einmal ab dem fruehesten betroffenen Datum ausgefuehrt.
     */
    protected bool $deferRecalculation = false;

    public function __construct(protected LoanRecalculationService $recalculation) {}

    /**
     * Mehrere Auszahlungen in einem Vorgang erfassen (Abschnitt 31):
     * ein Darlehen wird in der Praxis in Teilbetraegen zu verschiedenen
     * Tagen ausgezahlt; der Kapitalverlauf und damit die Zinsen folgen
     * diesen Tagen taggenau.
     *
     * Jede Zeile: [
     *   'planned_amount' => '50000.00', 'planned_date' => '2026-01-15',
     *   'confirmed' => bool, 'origin' => PaymentOrigin|string|null,
     *   'reference' => ?string, 'note' => ?string,
     * ]
     * Bestaetigte Zeilen werden geplant UND bestaetigt, damit die
     * Kapitalbuchung mit Wirkungsdatum = Auszahlungsdatum entsteht.
     * Am Ende folgt EINE Neuberechnung ab dem fruehesten Auszahlungsdatum.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, LoanDisbursement>
     */
    public function planMany(Loan $loan, array $rows, ?User $user = null): array
    {
        $created = [];
        $earliest = null;

        $this->deferRecalculation = true;

        try {
            foreach ($rows as $row) {
                $disbursement = $this->plan($loan, $row, $user);

                $dates = [Carbon::parse($row['planned_date'])->toDateString()];

                if (! empty($row['confirmed'])) {
                    $origin = $row['origin'] ?? PaymentOrigin::ManualEntered;
                    if (! $origin instanceof PaymentOrigin) {
                        $origin = PaymentOrigin::tryFrom((string) $origin) ?? PaymentOrigin::ManualEntered;
                    }
                    $this->confirm(
                        $disbursement,
                        Money::normalize($row['planned_amount']),
                        Carbon::parse($row['planned_date']),
                        $origin,
                        $user,
                    );
                }

                $created[] = $disbursement->refresh();
                $earliest = $earliest === null ? min($dates) : min($earliest, ...$dates);
            }
        } finally {
            $this->deferRecalculation = false;
        }

        if ($earliest !== null) {
            $this->recalculation->recalculate($loan, 'disbursements_recorded', Carbon::parse($earliest), $user);
        }

        return $created;
    }

    /**
     * Auszahlung planen. data: planned_amount, planned_date, optional
     * bank_account_id, reference, note. Liegt das Plandatum in der
     * Vergangenheit, setzt die anschliessende Neuberechnung die Auszahlung
     * gem. Abschnitt 24 auf systemseitig angenommen und bucht sie.
     */
    public function plan(Loan $loan, array $data, ?User $user = null): LoanDisbursement
    {
        $amount = Money::normalize($data['planned_amount'] ?? null);
        if (! Money::isPositive($amount)) {
            throw new \InvalidArgumentException('Der geplante Auszahlungsbetrag muss größer 0 sein.');
        }
        if (empty($data['planned_date'])) {
            throw new \InvalidArgumentException('Ein geplantes Auszahlungsdatum ist erforderlich.');
        }
        $plannedDate = Carbon::parse($data['planned_date'])->toDateString();

        $disbursement = DB::transaction(fn () => $loan->disbursements()->create([
            'planned_amount' => $amount,
            'planned_date' => $plannedDate,
            'status' => DisbursementStatus::Planned,
            'origin' => PaymentOrigin::Assumed,
            // Beide Kontoseiten (Abschnitt 31): von wo und wohin ausgezahlt wurde.
            'source_bank_account_id' => $data['source_bank_account_id'] ?? null,
            'target_bank_account_id' => $data['target_bank_account_id'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null, // überholt, nur Bestandsdaten
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'recorded_at' => now(),
        ]));

        AuditService::log('loans.disbursement_planned', $disbursement, [], [
            'planned_amount' => $amount,
            'planned_date' => $plannedDate,
        ]);

        $this->triggerRecalculation($loan, 'disbursement_planned', Carbon::parse($plannedDate), $user);

        return $disbursement->refresh();
    }

    /**
     * Auszahlung bestaetigen (IST erfassen). Eine zuvor angenommene Buchung
     * wird per Gegenbuchung neutralisiert; die bestaetigte Auszahlung wird
     * mit Wirkungsdatum = tatsaechlichem Datum gebucht.
     */
    public function confirm(LoanDisbursement $d, string $actualAmount, CarbonInterface $actualDate, PaymentOrigin $origin, ?User $user = null): void
    {
        $actual = Money::normalize($actualAmount);
        if (Money::isNegative($actual)) {
            throw new \InvalidArgumentException('Der tatsächliche Auszahlungsbetrag darf nicht negativ sein.');
        }
        $actualDateStr = Carbon::parse($actualDate->toDateString())->toDateString();

        $old = ['status' => $d->status->value, 'actual_amount' => $d->actual_amount];

        DB::transaction(function () use ($d, $actual, $actualDateStr, $origin, $user) {
            $this->reverseOwnBookings($d, $user, 'Korrektur vor Bestätigung der Auszahlung');

            if (Money::isPositive($actual)) {
                LoanTransaction::create([
                    'loan_id' => $d->loan_id,
                    'booking_type' => BookingType::Disbursement,
                    'booking_date' => today()->toDateString(),
                    'effective_date' => $actualDateStr,
                    'amount' => $actual,
                    'description' => 'Auszahlung bestätigt',
                    'source_type' => $d->getMorphClass(),
                    'source_id' => $d->id,
                    'created_by' => $user?->id ?? auth()->id(),
                    'created_at' => now(),
                ]);
            }

            $status = Money::cmp($actual, $d->planned_amount) >= 0
                ? DisbursementStatus::Confirmed
                : (Money::isPositive($actual) ? DisbursementStatus::Partial : DisbursementStatus::Failed);

            $d->update([
                'actual_amount' => $actual,
                'actual_date' => $actualDateStr,
                'status' => $status,
                'origin' => $origin,
            ]);
        });

        AuditService::log('loans.disbursement_confirmed', $d, $old, [
            'actual_amount' => $actual,
            'actual_date' => $actualDateStr,
            'status' => $d->status->value,
        ]);

        $earliest = min($d->planned_date->toDateString(), $actualDateStr);
        $this->triggerRecalculation($d->loan()->firstOrFail(), 'disbursement_confirmed', Carbon::parse($earliest), $user);
    }

    /**
     * Nicht erfolgte Auszahlung (Abschnitt 32): IST = 0, Status failed.
     * Eine bereits gebuchte (angenommene) Transaktion wird per Gegenbuchung
     * neutralisiert; Kapital, Zinsen und Folgewerte korrigiert die
     * anschliessende Neuberechnung automatisch.
     */
    public function markFailed(LoanDisbursement $d, ?string $note = null, ?User $user = null): void
    {
        $old = ['status' => $d->status->value, 'actual_amount' => $d->actual_amount];

        DB::transaction(function () use ($d, $note, $user) {
            $this->reverseOwnBookings($d, $user, 'Storno: Auszahlung nicht erfolgt');

            $d->update([
                'actual_amount' => '0.00',
                'actual_date' => null,
                'status' => DisbursementStatus::Failed,
                'origin' => PaymentOrigin::ManualEntered,
                'note' => trim(($d->note ? $d->note.' | ' : '').($note ?? 'Auszahlung nicht erfolgt')),
            ]);
        });

        AuditService::log('loans.disbursement_failed', $d, $old, ['note' => $note]);

        $this->triggerRecalculation($d->loan()->firstOrFail(), 'disbursement_failed', Carbon::parse($d->planned_date->toDateString()), $user);
    }

    /** Geplante Auszahlung stornieren; bereits Gebuchtes wird gegengebucht. */
    public function cancel(LoanDisbursement $d, ?string $reason = null, ?User $user = null): void
    {
        if ($d->status === DisbursementStatus::Cancelled) {
            throw new \InvalidArgumentException('Die Auszahlung ist bereits storniert.');
        }

        $old = ['status' => $d->status->value];

        DB::transaction(function () use ($d, $reason, $user) {
            $this->reverseOwnBookings($d, $user, 'Storno der Auszahlung'.($reason ? ': '.$reason : ''));

            $d->update([
                'status' => DisbursementStatus::Cancelled,
                'origin' => PaymentOrigin::Cancelled,
                'note' => trim(($d->note ? $d->note.' | ' : '').'Storniert'.($reason ? ': '.$reason : '')),
            ]);
        });

        AuditService::log('loans.disbursement_cancelled', $d, $old, ['reason' => $reason]);

        $this->triggerRecalculation($d->loan()->firstOrFail(), 'disbursement_cancelled', Carbon::parse($d->planned_date->toDateString()), $user);
    }

    /**
     * Neuberechnung anstossen, sofern kein Sammelvorgang laeuft
     * (Abschnitt 35: jede kapitalwirksame Aktion rechnet neu; ein
     * Sammelvorgang rechnet gebuendelt genau einmal am Ende).
     */
    protected function triggerRecalculation(Loan $loan, string $trigger, CarbonInterface $earliest, ?User $user): void
    {
        if ($this->deferRecalculation) {
            return;
        }

        $this->recalculation->recalculate($loan, $trigger, $earliest, $user);
    }

    /**
     * Neutralisiert alle eigenen, noch nicht stornierten
     * Auszahlungsbuchungen per Gegenbuchung (append-only, Abschnitt 49).
     * Wirkungsdatum der Gegenbuchung = Wirkungsdatum der Originalbuchung.
     */
    protected function reverseOwnBookings(LoanDisbursement $d, ?User $user, string $description): void
    {
        $own = LoanTransaction::query()
            ->where('source_type', $d->getMorphClass())
            ->where('source_id', $d->id)
            ->orderBy('id')
            ->get();

        $reversedIds = $own->pluck('reversal_of')->filter()->all();

        foreach ($own as $tx) {
            if ($tx->booking_type !== BookingType::Disbursement || in_array($tx->id, $reversedIds, true)) {
                continue;
            }
            LoanTransaction::create([
                'loan_id' => $tx->loan_id,
                'booking_type' => BookingType::Cancellation,
                'booking_date' => today()->toDateString(),
                'effective_date' => $tx->effective_date->toDateString(),
                'amount' => Money::negate($tx->amount),
                'description' => $description,
                'source_type' => $d->getMorphClass(),
                'source_id' => $d->id,
                'reversal_of' => $tx->id,
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);
        }
    }
}
