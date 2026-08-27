<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // Link + ownership
            $table->uuid('token')->unique();                   // client fill link: /apply/{token}
            $table->string('verification_code', 32)->unique(); // QR verify: /verify/{code}
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 5)->default('en');        // en | es
            $table->string('status')->default('draft');        // draft|submitted|in_review|quoted|signed|issued

            // Applicant Information
            $table->string('company_name')->nullable();
            $table->string('company_representative')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('parking_address')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('us_dot_number')->nullable();
            $table->string('radius_of_operations')->nullable();
            $table->string('years_in_business')->nullable();
            $table->unsignedInteger('power_units')->nullable();
            $table->text('commodities_hauled')->nullable();

            // Finance Proposal
            $table->decimal('total_policy_premium', 12, 2)->nullable();

            // Commercial Auto Application / Agency
            $table->string('agency_name')->nullable();
            $table->string('agency_phone')->nullable();
            $table->string('contact_agent_name')->nullable();

            // Disclosure + signature
            $table->string('signer_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->timestamp('disclosure_accepted_at')->nullable();

            // Generated document
            $table->string('pdf_path')->nullable();

            // Status timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('in_review_at')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('issued_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
