<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausfall eines Darlehens (Anforderung vom 30.08.2026).
 *
 * Rein additiv. NICHT zu verwechseln mit den Verzugszinsen
 * (default_interest_*): dort geht es um den Verzug, hier um den Ausfall der
 * Forderung.
 *
 * - defaulted_on:    Ausfalldatum als Wirkungsdatum. Ab diesem Tag werden
 *                    keine weiteren Soll-Zinsen erzeugt; bereits entstandene
 *                    bleiben erhalten. Zinsen nach dem Ausfall wären eine
 *                    Forderung, die das System nicht unterstellen darf.
 * - default_reason:  Grund der Erfassung, fachliche Angabe des Bearbeiters.
 *
 * Es findet keine rechtliche Bewertung und keine automatische Einstufung als
 * uneinbringlich statt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('defaulted_on')->nullable();
            $table->text('default_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['defaulted_on', 'default_reason']);
        });
    }
};
