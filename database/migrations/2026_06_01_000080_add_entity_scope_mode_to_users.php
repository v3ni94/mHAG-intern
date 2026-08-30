<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sichtbarkeitsmodus je Benutzer (Anforderung vom 30.08.2026).
 *
 * Bisher gilt für externe Rollen ausschließlich: sichtbar ist, was
 * ausdrücklich zugeordnet ist. Für Partner ist die umgekehrte Vorgabe
 * erforderlich: alles sehen außer bestimmten Gesellschaften.
 *
 * - include (Standard, bisheriges Verhalten): sichtbar sind nur die
 *   zugeordneten Gesellschaften.
 * - exclude: sichtbar ist alles außer den zugeordneten Gesellschaften.
 *   Neu angelegte Gesellschaften sind damit automatisch sichtbar, was der
 *   fachlichen Erwartung "alles außer X" entspricht.
 *
 * Interne Rollen sind davon unberührt, sie sehen weiterhin den
 * Gesamtbestand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('entity_scope_mode')->default('include');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('entity_scope_mode');
        });
    }
};
