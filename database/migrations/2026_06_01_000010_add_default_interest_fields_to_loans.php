<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verzugszinsen (Abschnitt 44 Masterprompt): fachliche Vorgaben je Darlehen.
 *
 * Rein additiv, bestehende Spalten bleiben unberührt. Es wird KEIN
 * gesetzlicher Verzugszinssatz vorbelegt und kein Verzugsbeginn geraten
 * (Abschnitte 44, 133, 140): ohne erfassten Satz und ohne erfassten
 * Verzugsbeginn berechnet und bucht das System nichts.
 *
 * - default_interest_start:  Verzugsbeginn (fachlich vorzugeben)
 * - default_interest_basis:  Berechnungsgrundlage
 *                            overdue_total     = alle überfälligen Positionen
 *                            overdue_principal = nur überfällige Tilgung
 * - default_interest_method: eigene Zinsmethode; null = Zinsmethode des Darlehens
 * - default_interest_mode:   manual    = nur auf ausdrückliche Anforderung
 *                            automatic = bei jeder Neuberechnung fortschreiben
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('default_interest_start')->nullable();
            $table->string('default_interest_basis')->default('overdue_total');
            $table->string('default_interest_method')->nullable();
            $table->string('default_interest_mode')->default('manual');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'default_interest_start',
                'default_interest_basis',
                'default_interest_method',
                'default_interest_mode',
            ]);
        });
    }
};
