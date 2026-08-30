<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('doc_type')->default('other');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->string('sha256', 64)->index();
            $table->date('document_date')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('storage_disk')->default('documents');
            $table->string('storage_path');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('active'); // active|archived|deleted
            $table->date('expires_on')->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('stored_filename');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->morphs('linkable');
            $table->timestamps();
            $table->unique(['document_id', 'linkable_type', 'linkable_id'], 'document_links_unique');
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained()->cascadeOnDelete();
            $table->string('version'); // z. B. 1.0, 1.1, 2.0
            $table->longText('body');   // HTML mit {{platzhalter}}
            $table->json('placeholders')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['contract_template_id', 'version']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_template_version_id')->nullable()->constrained('contract_template_versions')->nullOnDelete();
            $table->string('title');
            $table->longText('body_snapshot')->nullable(); // Snapshot: Vorlagenänderungen ändern alte Verträge nie
            $table->string('status')->default('draft');    // draft|final|signed|cancelled
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete(); // erzeugtes PDF
            $table->timestamps();
        });

        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('amendment_type'); // term_extension|rate_change|repayment_change|deferral|principal_change|security_change|other
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->longText('body_snapshot')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_template_versions');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
