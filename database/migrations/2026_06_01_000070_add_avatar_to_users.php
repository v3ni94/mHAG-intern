<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profilbild je Benutzer (Anforderung vom 30.08.2026).
 *
 * Gespeichert wird ausschließlich der Ablagepfad, nie das Bild selbst. Die
 * Datei liegt außerhalb des öffentlichen Verzeichnisses und wird über einen
 * berechtigungsgeprüften Controller ausgeliefert (Abschnitt 64: keine
 * öffentlich erreichbaren Dateien).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
