<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('down_payment', 12, 2)->nullable()->after('total_policy_premium');
            $table->unsignedSmallInteger('number_of_payments')->nullable()->after('down_payment');
            $table->decimal('monthly_payment', 12, 2)->nullable()->after('number_of_payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['down_payment', 'number_of_payments', 'monthly_payment']);
        });
    }
};
