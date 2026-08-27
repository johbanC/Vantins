<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('applications')->where('status', 'draft')->update(['status' => 'created']);
        DB::table('applications')->where('status', 'submitted')->update(['status' => 'signed']);
    }

    public function down(): void
    {
        DB::table('applications')->where('status', 'created')->update(['status' => 'draft']);
        // 'signed' is intentionally left as-is (was a valid status before the remap too).
    }
};
