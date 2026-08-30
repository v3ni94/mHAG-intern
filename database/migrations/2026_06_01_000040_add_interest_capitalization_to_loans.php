<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zinskapitalisierung (Anforderung vom 30.08.2026).
 *
 * Rein additiv, Standard aus: bestehende Darlehen rechnen unverändert weiter.
 *
 * - interest_capitalization:      Fällige Zinsen werden nicht als Zahlung
 *                                 erwartet, sondern dem valutierten Betrag
 *                                 zugeschrieben.
 * - interest_capitalization_from: Wirkungsdatum der Umstellung. Zugeschrieben
 *                                 werden nur Perioden, deren Fälligkeit nicht
 *                                 vor diesem Tag liegt. Ohne Angabe gilt der
 *                                 Wirkungsbeginn des Darlehens, das Darlehen
 *                                 kapitalisiert also von Anfang an.
 *                                 Frühere Perioden bleiben unverändert
 *                                 (Trennung Wirkungsdatum und Erfassungsdatum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('interest_capitalization')->default(false);
            $table->date('interest_capitalization_from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['interest_capitalization', 'interest_capitalization_from']);
        });
    }
};
