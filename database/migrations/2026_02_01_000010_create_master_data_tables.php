<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zentraler Entity-/Geschäftspartnerstamm (Abschnitt 5 Masterprompt)
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // person|company|organization
            $table->string('display_name')->index();
            $table->string('status')->default('active'); // active|archived
            $table->string('internal_number')->nullable()->unique();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable(); // interne Notizen, nie für externe Rollen sichtbar
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('salutation')->nullable();
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('middle_names')->nullable();
            $table->string('last_name');
            $table->string('birth_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->date('founded_on')->nullable();
            $table->string('commercial_register')->nullable(); // z. B. HRB
            $table->string('register_number')->nullable();
            $table->string('register_court')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('vat_id')->nullable();
            $table->string('business_id')->nullable(); // Wirtschafts-ID
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('industry')->nullable();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('main'); // main|secondary|business|correspondence|historical
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('addition')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Deutschland');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('contact_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // email|email_alt|phone|mobile|fax|other
            $table->string('value');
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('account_holder');
            $table->string('iban')->index();
            $table->string('bic')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('tax_id')->nullable();      // Steuer-ID
            $table->string('tax_number')->nullable();  // Steuernummer
            $table->string('tax_office')->nullable();  // Finanzamt
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // id_card|passport|residence_permit|drivers_license|other
            $table->string('document_number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index(); // löst Wiedervorlagen aus
            $table->string('authority')->nullable();
            $table->string('country')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('entity_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_a_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('entity_b_id')->constrained('entities')->cascadeOnDelete();
            $table->string('relationship_type'); // parent|subsidiary|sister|investment|joint_venture|affiliated|other
            $table->decimal('share_percentage', 9, 6)->nullable();
            $table->unsignedBigInteger('share_count')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Organstellungen / Funktionen von Personen in Unternehmen (Historie nie überschreiben)
        Schema::create('organization_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('person_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('role'); // managing_director|board_member|authorized_officer|supervisory_board_member|supervisory_board_chair|advisory_board|shareholder|stockholder|contact_person
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('sole_representation')->nullable();
            $table->string('representation_rule')->nullable();
            $table->boolean('exemption_181')->nullable(); // reine Information, keine rechtliche Bewertung
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Nachträglicher FK von users.entity_id auf entities
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('entity_id')->references('id')->on('entities')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['entity_id']);
            });
        }
        Schema::dropIfExists('organization_roles');
        Schema::dropIfExists('entity_relationships');
        Schema::dropIfExists('identity_documents');
        Schema::dropIfExists('tax_details');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('contact_details');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('entities');
    }
};
