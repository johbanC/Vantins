<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Insurance companies. Internal only: the client never sees these names.
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // One quote per carrier / alternative, kept as separate records. A revision is a new
        // row (version + 1) that points at the one it replaces, nothing is overwritten.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('producer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('previous_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();

            $table->string('product')->nullable();
            $table->string('stage', 30)->default('lead')->index();
            $table->boolean('is_demo')->default(false)->index();

            // Lead qualification
            $table->text('qualification_note')->nullable();
            $table->string('next_step')->nullable();
            $table->date('follow_up_at')->nullable()->index();

            // The offer
            $table->date('quoted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->date('effective_date')->nullable();
            $table->decimal('carrier_premium', 12, 2)->nullable();
            $table->decimal('fees', 12, 2)->nullable();
            $table->decimal('producer_fee', 12, 2)->nullable();
            $table->decimal('down_payment', 12, 2)->nullable();
            $table->unsignedSmallInteger('number_of_payments')->nullable();
            $table->decimal('installment_amount', 12, 2)->nullable();

            // Later stages
            $table->string('binder_number')->nullable();
            $table->date('binder_effective_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('policy_number')->nullable();
            $table->date('closed_at')->nullable();
            $table->string('loss_reason', 40)->nullable();
            $table->text('loss_note')->nullable();
            $table->string('competitor')->nullable();

            // The client's second signature, on the formal proposal (separate from the application's)
            $table->uuid('acceptance_token')->nullable()->unique();
            $table->timestamp('acceptance_expires_at')->nullable();
            $table->timestamp('acceptance_revoked_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_signer_name')->nullable();
            $table->string('accepted_signature_path')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->json('accepted_snapshot')->nullable();   // the exact terms the client signed

            $table->timestamps();

            $table->index(['application_id', 'stage']);
        });

        // Coverages as this carrier quoted them: its own limits, deductibles and price.
        Schema::create('quote_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coverage_type_id')->nullable()->constrained('coverage_types')->restrictOnDelete();
            $table->string('custom_name')->nullable();
            $table->decimal('limit_amount', 14, 2)->nullable();
            $table->decimal('aggregate_limit', 14, 2)->nullable();
            $table->decimal('deductible', 12, 2)->nullable();
            $table->decimal('premium', 12, 2)->nullable();
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Append-only: who moved the quote to which stage, and when.
        Schema::create('quote_stage_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->text('note')->nullable();
            $table->json('evidence')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Files received / sent: proposals, signed acceptance, binder, payment receipts.
        Schema::create('quote_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The alternative handed to the client.
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('selected_quote_id')->nullable()->after('client_id')->constrained('quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $t) => $t->dropConstrainedForeignId('selected_quote_id'));
        Schema::dropIfExists('quote_documents');
        Schema::dropIfExists('quote_stage_changes');
        Schema::dropIfExists('quote_coverages');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('carriers');
    }
};
