<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dauerhafte Verknüpfung eines Intranet-Benutzers mit seinem Konto im CRM.
 *
 * Die Zuordnung erfolgt beim ersten Mal über die E-Mail-Adresse und wird
 * danach über diese Kennung geführt. Damit bleibt die Verknüpfung bestehen,
 * wenn sich die E-Mail-Adresse ändert, und eine später im CRM neu vergebene
 * Adresse führt nicht versehentlich in ein fremdes Konto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('crm_subject', 100)->nullable()->unique()->after('email');
            $table->timestamp('crm_linked_at')->nullable()->after('crm_subject');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['crm_subject']);
            $table->dropColumn(['crm_subject', 'crm_linked_at']);
        });
    }
};
