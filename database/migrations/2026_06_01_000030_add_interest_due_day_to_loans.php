<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einstellbarer Fälligkeitstag der Zinsen (Anforderung vom 30.08.2026).
 *
 * Rein additiv. Der Standardwert entspricht dem bisherigen Verhalten, damit
 * bestehende Darlehen unverändert weiterrechnen:
 *
 * - interest_due_day_mode: effective_from (Standard), fixed_day, month_end
 * - interest_due_day:      fester Tag im Monat, nur bei fixed_day,
 *                          zulässig 1 bis 28. Ein fester 29., 30. oder 31.
 *                          existiert nicht in jedem Monat und führt zu
 *                          uneinheitlichen Perioden; wer den Monatsletzten
 *                          möchte, wählt month_end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('interest_due_day_mode')->default('effective_from');
            $table->unsignedTinyInteger('interest_due_day')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['interest_due_day_mode', 'interest_due_day']);
        });
    }
};
