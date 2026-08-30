<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zuordnung Benutzer -> Entities (Datenscope / Kontextwechsel)
        Schema::create('user_entity_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('context')->default('self'); // self|company|supervisory_board
            $table->string('label')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'entity_id', 'context']);
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('email');
            $table->string('token_hash', 64)->unique(); // sha256, nie Klartext speichern
            $table->json('roles')->nullable();
            $table->json('entity_ids')->nullable(); // Datenbereich
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('auditable');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            // bewusst kein updated_at: Audit-Einträge sind unveränderlich
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['group', 'key']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->index();
            $table->time('due_time')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('normal'); // low|normal|high
            $table->string('status')->default('open');     // open|done|cancelled
            $table->nullableMorphs('remindable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_facts', function (Blueprint $table) {
            $table->id();
            $table->string('month_day', 5)->index(); // 'MM-TT', z. B. '08-30'
            $table->date('specific_date')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source'); // Pflicht: keine erfundenen Einträge
            $table->string('country')->nullable();
            $table->boolean('recurring')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('faq_entries', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->string('visibility')->default('all'); // all|internal|admin|supervisory_board|lender|borrower
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->date('released_on');
            $table->text('changes'); // Markdown
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
        Schema::dropIfExists('faq_entries');
        Schema::dropIfExists('daily_facts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('user_entity_assignments');
    }
};
