<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fälligkeitsmonat der Zinsen (Ergänzung vom 30.08.2026).
 *
 * Ohne dieses Feld richtet sich der Monat der Fälligkeit bei jährlicher,
 * halbjährlicher und quartalsweiser Zinsfälligkeit nach dem Wirkungsbeginn.
 * Eine Vereinbarung wie "Zinsen jährlich zum 31.12." ist damit nicht
 * darstellbar, wenn das Darlehen nicht im Dezember beginnt.
 *
 * - interest_due_month (1 bis 12, nullable): Ankermonat des
 *   Fälligkeitsrasters. Wirkt nur bei einer Zinsfälligkeit von drei Monaten
 *   und mehr und nur zusammen mit einem festen Tag oder dem Monatsletzten.
 *   Ohne Angabe bleibt das bisherige Verhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedTinyInteger('interest_due_month')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('interest_due_month');
        });
    }
};
