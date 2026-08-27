<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('applications')->update(['agency_name' => config('vantins.agency_name')]);
    }

    public function down(): void
    {
        // no-op
    }
};
