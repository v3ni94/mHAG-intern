<?php

namespace App\Models;

use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Enums\RepaymentItemType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepaymentPlanItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'item_type' => RepaymentItemType::class,
            'due_date' => 'date',
            'planned_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'status' => RepaymentItemStatus::class,
            'origin' => PaymentOrigin::class,
            'actual_date' => 'date',
            'value_date' => 'date',
            'manually_adjusted' => 'boolean',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * IST-Betrag unter Berücksichtigung der systemseitigen Annahme:
     * Solange keine Abweichung erfasst ist, gilt planmäßige Erfüllung
     * als angenommen (Abschnitt 24), deutlich gekennzeichnet über origin.
     *
     * "Geplant" wird dabei wie "Systemseitig angenommen" behandelt. Die
     * Zuordnung steht ausschließlich in RepaymentItemStatus, damit dieses
     * Modell und LoanBalanceService nicht auseinanderlaufen.
     */
    public function effectiveActual(): string
    {
        $status = $this->status;

        if (! $status instanceof RepaymentItemStatus) {
            return '0.00';
        }

        if ($status->giltAlsErfuelltDurchAnnahme()) {
            return Money::normalize($this->planned_amount);
        }

        if ($status->hatBestaetigtenIst()) {
            return Money::normalize($this->actual_amount);
        }

        return '0.00';
    }

    /** Bestätigter IST-Betrag, ohne jede Annahme. */
    public function confirmedActual(): string
    {
        $status = $this->status;

        return $status instanceof RepaymentItemStatus && $status->hatBestaetigtenIst()
            ? Money::normalize($this->actual_amount)
            : '0.00';
    }

    /**
     * Offener Betrag im Sinne der Bücher.
     *
     * Dieselbe Rechnung wie in LoanBalanceService: eine nur angenommene
     * Erfüllung gilt als Erfüllung, eine erlassene oder stornierte Position
     * schuldet nichts. Dieser Wert steht in den Kennzahlen und in der
     * Forderungsaufstellung.
     */
    public function openAmount(): string
    {
        $status = $this->status;

        if ($status instanceof RepaymentItemStatus && $status->istAbgeschlossenOhneZahlung()) {
            return '0.00';
        }

        $open = Money::sub($this->planned_amount, $this->effectiveActual());

        return Money::isNegative($open) ? '0.00' : $open;
    }

    /**
     * Erwarteter Zahlungsbetrag, ohne die Annahme der Erfüllung.
     *
     * Für Liquiditätsplanung, Fälligkeiten und Überfälligkeitsmeldungen: dort
     * ist die Frage nicht, was die Bücher als offen führen, sondern welcher
     * Betrag noch tatsächlich fließen muss. Eine nur angenommene Erfüllung ist
     * hier kein Geldeingang.
     *
     * Bewusst ein eigener Name. Beide Zahlen sind richtig, sie beantworten
     * verschiedene Fragen; sie unter einem Namen zu führen war der Fehler.
     */
    public function expectedAmount(): string
    {
        $status = $this->status;

        if ($status instanceof RepaymentItemStatus && $status->istAbgeschlossenOhneZahlung()) {
            return '0.00';
        }

        $offen = Money::sub($this->planned_amount, $this->confirmedActual());

        return Money::isNegative($offen) ? '0.00' : $offen;
    }
}
