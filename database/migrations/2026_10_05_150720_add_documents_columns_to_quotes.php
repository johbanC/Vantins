<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // Printed on the proposal / binder and read by the QR code, like the application's.
            $table->string('verification_code', 20)->nullable()->unique()->after('version');
            // The PDF exactly as the client signed it (private disk).
            $table->string('accepted_pdf_path')->nullable()->after('accepted_snapshot');
            // An admin authorises showing the carrier's name on the binder; hidden by default.
            $table->boolean('carrier_disclosed')->default(false)->after('producer_id');
        });

        foreach (DB::table('quotes')->whereNull('verification_code')->pluck('id') as $id) {
            DB::table('quotes')->where('id', $id)->update(['verification_code' => strtoupper(Str::random(10))]);
        }
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['verification_code', 'accepted_pdf_path', 'carrier_disclosed']);
        });
    }
};
