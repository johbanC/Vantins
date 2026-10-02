<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->date('cdl_issue_date')->nullable()->after('state_issued');
            $table->date('cdl_expiry_date')->nullable()->after('cdl_issue_date');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('garaging_zip', 10)->nullable()->after('body_type');
            $table->boolean('has_physical_damage')->default(false)->after('stated_value');
            $table->decimal('physical_damage_value', 12, 2)->nullable()->after('has_physical_damage');
            $table->decimal('physical_damage_deductible', 10, 2)->nullable()->after('physical_damage_value');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status')->index();
        });

        // Every record that exists today was created while testing.
        DB::table('applications')->update(['is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $t) => $t->dropColumn('is_demo'));
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn([
            'garaging_zip', 'has_physical_damage', 'physical_damage_value', 'physical_damage_deductible',
        ]));
        Schema::table('drivers', fn (Blueprint $t) => $t->dropColumn(['cdl_issue_date', 'cdl_expiry_date']));
    }
};
