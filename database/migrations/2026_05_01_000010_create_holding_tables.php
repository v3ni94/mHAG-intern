<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shareholders', function (Blueprint $table) {
            $table->id();
            $table->string('shareholder_number')->unique();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Aktienbestand wird NIE direkt gespeichert, sondern immer aus wirksamen Bewegungen berechnet
        Schema::create('share_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->string('type'); // purchase|sale|transfer|gift|redemption|capital_increase|capital_decrease|correction|other
            $table->foreignId('seller_shareholder_id')->nullable()->constrained('shareholders')->nullOnDelete();
            $table->foreignId('buyer_shareholder_id')->nullable()->constrained('shareholders')->nullOnDelete();
            $table->unsignedBigInteger('share_count');
            $table->decimal('price_per_share', 18, 4)->nullable();
            $table->decimal('total_price', 18, 2)->nullable();
            $table->date('contract_date')->nullable();
            $table->date('economic_transfer_date'); // wirtschaftlicher Übergang = Wirkungsdatum
            $table->date('booking_date')->nullable();
            $table->foreignId('resolution_id')->nullable();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft|review|contract_created|for_signature|signed|resolved|effective|cancelled
            $table->foreignId('reversal_of')->nullable()->constrained('share_transactions')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'economic_transfer_date']);
        });

        Schema::create('shareholder_list_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->date('as_of_date');
            $table->json('data'); // unveränderlicher Daten-Snapshot
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sha256', 64)->nullable();
            $table->string('signature_status')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->decimal('share_percentage', 9, 6)->nullable();
            $table->unsignedBigInteger('share_count')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('acquisition_cost', 18, 2)->nullable();
            $table->decimal('current_value', 18, 2)->nullable(); // nur manuell gepflegt, nie erfunden
            $table->string('status')->default('active'); // active|sold|liquidated
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('corporate_bodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('type'); // board|supervisory_board|advisory_board
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('corporate_body_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_body_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('role')->nullable(); // Funktion, z. B. Vorstand, Mitglied
            $table->boolean('is_chair')->default(false);
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable()->index(); // Mandatsende -> Wiedervorlage
            $table->string('status')->default('active'); // active|ended
            $table->string('representation')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('resolution_number')->unique();
            $table->string('title');
            $table->foreignId('company_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('type'); // board|supervisory_board|general_meeting|circular|other
            $table->foreignId('applicant_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->text('motion')->nullable();     // Antrag
            $table->text('reasoning')->nullable();  // Begründung
            $table->text('resolution_text')->nullable();
            $table->date('resolved_on')->nullable();   // tatsächliches Beschlussdatum
            $table->timestamp('recorded_at')->nullable(); // technisches Erfassungsdatum
            $table->string('result')->nullable(); // accepted|rejected|postponed|withdrawn
            $table->string('status')->default('draft'); // draft|submitted|review|voting|accepted|rejected|postponed|withdrawn|for_signature|signed|completed|archived
            $table->boolean('conflict_of_interest')->default(false);
            $table->text('conflict_notes')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('resolution_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('attended')->nullable();
            $table->boolean('excluded_from_deliberation')->default(false); // Interessenkonflikt
            $table->boolean('excluded_from_vote')->default(false);
            $table->timestamps();
        });

        Schema::create('resolution_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resolution_participant_id')->constrained('resolution_participants')->cascadeOnDelete();
            $table->string('vote')->nullable(); // yes|no|abstain|absent
            $table->timestamps();
        });

        Schema::create('resolution_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained()->cascadeOnDelete();
            $table->morphs('linkable');
            $table->timestamps();
        });

        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // Resolution, Contract, ShareholderListSnapshot ...
            $table->string('provider')->default('manual');
            $table->string('external_id')->nullable();
            $table->string('status')->default('draft'); // draft|sent|in_progress|completed|declined|expired|error
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete(); // signiertes PDF
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('signature_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable(); // Vorstand, Aufsichtsratsvorsitzender, Käufer ...
            $table->string('email')->nullable();
            $table->string('status')->default('not_sent'); // not_sent|sent|opened|signed|declined|expired|error
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('share_transactions', function (Blueprint $table) {
                $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('share_transactions', function (Blueprint $table) {
                $table->dropForeign(['resolution_id']);
            });
        }
        Schema::dropIfExists('signature_participants');
        Schema::dropIfExists('signature_requests');
        Schema::dropIfExists('resolution_links');
        Schema::dropIfExists('resolution_votes');
        Schema::dropIfExists('resolution_participants');
        Schema::dropIfExists('resolutions');
        Schema::dropIfExists('corporate_body_members');
        Schema::dropIfExists('corporate_bodies');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('shareholder_list_snapshots');
        Schema::dropIfExists('share_transactions');
        Schema::dropIfExists('shareholders');
    }
};
