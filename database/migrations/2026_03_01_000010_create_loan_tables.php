<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->string('title')->nullable();
            $table->foreignId('lender_entity_id')->constrained('entities');
            $table->foreignId('borrower_entity_id')->constrained('entities');
            $table->foreignId('loan_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_basis')->nullable();
            $table->date('contract_date')->nullable();
            $table->date('effective_from'); // Wirkungsbeginn (fachlich)
            $table->date('disbursement_date')->nullable();
            $table->unsignedInteger('term_months')->nullable();
            $table->date('due_date')->nullable();
            $table->string('notice_period')->nullable();
            $table->date('contract_end')->nullable();
            $table->decimal('principal_amount', 18, 2); // ursprüngliche Darlehenssumme
            $table->decimal('credit_limit', 18, 2)->nullable(); // Darlehensrahmen
            $table->string('currency', 3)->default('EUR');
            $table->string('interest_method')->default('act_365'); // act_365|act_360|thirty_360|act_act
            $table->string('interest_frequency')->default('monthly'); // monthly|quarterly|semiannual|annual|at_maturity|custom
            $table->string('repayment_model')->default('bullet'); // bullet|installment|annuity|custom|open_ended|frame|current_account
            $table->decimal('default_interest_rate', 9, 6)->nullable(); // Verzugszins: nur wenn Nutzer vorgibt
            $table->boolean('default_interest_enabled')->default(false);
            $table->string('status')->default('draft');
            $table->string('risk_rating')->nullable(); // very_low|low|medium|elevated|high – nur manuell, intern
            $table->foreignId('handler_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project')->nullable();
            $table->string('cost_center')->nullable();
            $table->json('tags')->nullable();
            $table->text('internal_notes')->nullable(); // nie für externe Rollen sichtbar
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'due_date']);
        });

        Schema::create('loan_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Zinssätze historisiert; Staffelzins = mehrere Zeilen (Abschnitt 40)
        Schema::create('loan_interest_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 9, 6); // Prozent p. a., z. B. 6.000000
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // processing|commitment|contract|administration|extension|other
            $table->string('name');
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('percentage', 9, 6)->nullable();
            $table->string('recurrence')->default('one_time'); // one_time|monthly|quarterly|annual
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        // Auszahlungen: SOLL und IST getrennt (Abschnitt 31/32)
        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_amount', 18, 2);
            $table->decimal('actual_amount', 18, 2)->nullable();
            $table->date('planned_date');
            $table->date('actual_date')->nullable();
            $table->string('status')->default('planned'); // planned|assumed|confirmed|partial|failed|cancelled
            $table->string('origin')->default('assumed'); // assumed|manual_confirmed|manual_entered|bank_import|corrected|cancelled
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('recorded_at')->nullable(); // Erfassungsdatum (technisch)
            $table->timestamps();
        });

        // Zahlungsplan: SOLL-Positionen mit IST-Erfassung je Position (Abschnitte 23-29, 45)
        Schema::create('repayment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('item_type'); // interest|principal|fee
            $table->date('due_date')->index();
            $table->decimal('planned_amount', 18, 2);
            $table->decimal('actual_amount', 18, 2)->nullable();
            $table->string('status')->default('planned'); // planned|assumed|confirmed|partial|missed|late|waived|cancelled
            $table->string('origin')->default('assumed');
            $table->date('actual_date')->nullable();
            $table->date('value_date')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('manually_adjusted')->default(false); // von Recalculation nicht überschreiben
            $table->timestamps();
            $table->index(['loan_id', 'item_type', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('payee_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->date('payment_date');
            $table->date('value_date')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('direction')->default('incoming'); // incoming|outgoing
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->string('reference')->nullable();
            $table->string('origin')->default('manual_entered');
            $table->string('status')->default('recorded'); // recorded|cancelled
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('bucket'); // costs|fees|default_interest|interest|principal|other
            $table->decimal('amount', 18, 2);
            $table->foreignId('repayment_plan_item_id')->nullable()->constrained('repayment_plan_items')->nullOnDelete();
            $table->timestamps();
        });

        // Darlehenskonto: append-only, signierte Beträge aus Forderungssicht (Abschnitte 48/49)
        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('booking_type'); // disbursement|repayment|interest_charge|interest_payment|fee_charge|fee_payment|default_interest|cancellation|correction|write_off|other
            $table->date('booking_date');
            $table->date('effective_date')->index(); // Wirkungsdatum
            $table->decimal('amount', 18, 2); // + erhöht Forderung, - reduziert Forderung
            $table->string('description')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of')->nullable()->constrained('loan_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['loan_id', 'effective_date']);
        });

        Schema::create('loan_recalculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_action');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('earliest_affected_date')->nullable();
            $table->json('old_state')->nullable();
            $table->json('new_state')->nullable();
            $table->string('status')->default('ok'); // ok|error
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('securities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('type'); // guarantee|land_charge|mortgage|chattel_transfer|pledge|assignment|company_shares|shares|real_estate|vehicle|other
            $table->decimal('nominal_value', 18, 2)->nullable();
            $table->decimal('internal_value', 18, 2)->nullable();
            $table->string('rank')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active|released|expired
            $table->timestamps();
        });

        Schema::create('guarantees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guarantor_entity_id')->constrained('entities');
            $table->string('guarantee_type')->nullable();
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->string('status')->default('active'); // active|released|expired
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantees');
        Schema::dropIfExists('securities');
        Schema::dropIfExists('loan_recalculations');
        Schema::dropIfExists('loan_transactions');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('repayment_plan_items');
        Schema::dropIfExists('loan_disbursements');
        Schema::dropIfExists('loan_fees');
        Schema::dropIfExists('loan_interest_terms');
        Schema::dropIfExists('loan_status_history');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_types');
    }
};
