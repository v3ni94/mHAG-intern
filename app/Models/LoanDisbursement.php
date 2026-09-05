<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentOrigin;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDisbursement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'planned_date' => 'date',
            'actual_date' => 'date',
            'status' => DisbursementStatus::class,
            'origin' => PaymentOrigin::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * ÜBERHOLT: einseitige Kontoangabe der ersten Fassung. Bleibt für die
     * Historie erhalten; gepflegt werden sourceBankAccount und targetBankAccount.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** Konto, von dem ausgezahlt wurde (Konto des Darlehensgebers). */
    public function sourceBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'source_bank_account_id');
    }

    /** Konto, auf das ausgezahlt wurde (Konto des Darlehensnehmers). */
    public function targetBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'target_bank_account_id');
    }

    /** Wirksam ausgezahlter Betrag (IST bzw. Annahme laut Status). */
    public function effectiveAmount(): string
    {
        return match ($this->status) {
            DisbursementStatus::Confirmed,
            DisbursementStatus::Partial => Money::normalize($this->actual_amount),
            DisbursementStatus::Assumed => Money::normalize($this->planned_amount),
            default => '0.00',
        };
    }
}
