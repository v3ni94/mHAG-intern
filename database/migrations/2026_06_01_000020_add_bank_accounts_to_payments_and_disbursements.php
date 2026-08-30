<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zahlungsverkehr: beide Kontoseiten je Vorgang (Abschnitte 31/46).
 *
 * Rein additiv:
 * - payments: payer_bank_account_id (Konto des Zahlers) und
 *   payee_bank_account_id (Konto des Empfängers)
 * - loan_disbursements: source_bank_account_id (Konto des Darlehensgebers)
 *   und target_bank_account_id (Konto des Darlehensnehmers)
 *
 * Kein Datenverlust: vorhandene Werte der bisherigen Spalte bank_account_id
 * werden in payer_bank_account_id bzw. source_bank_account_id übernommen.
 * Die Spalte bank_account_id bleibt erhalten (Historie und bestehende
 * Verweise), gilt aber als ÜBERHOLT und wird nicht mehr gepflegt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payer_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('payee_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });

        Schema::table('loan_disbursements', function (Blueprint $table) {
            $table->foreignId('source_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('target_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });

        // Bestandsdaten übernehmen (überholte Spalte bleibt unverändert erhalten)
        DB::table('payments')
            ->whereNotNull('bank_account_id')
            ->update(['payer_bank_account_id' => DB::raw('bank_account_id')]);

        DB::table('loan_disbursements')
            ->whereNotNull('bank_account_id')
            ->update(['source_bank_account_id' => DB::raw('bank_account_id')]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payer_bank_account_id']);
            $table->dropForeign(['payee_bank_account_id']);
            $table->dropColumn(['payer_bank_account_id', 'payee_bank_account_id']);
        });

        Schema::table('loan_disbursements', function (Blueprint $table) {
            $table->dropForeign(['source_bank_account_id']);
            $table->dropForeign(['target_bank_account_id']);
            $table->dropColumn(['source_bank_account_id', 'target_bank_account_id']);
        });
    }
};
