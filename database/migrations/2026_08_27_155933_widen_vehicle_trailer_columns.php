<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['vehicles', 'trailers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('year', 20)->nullable()->change();
                $t->string('vin', 64)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['vehicles', 'trailers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('year', 4)->nullable()->change();
                $t->string('vin', 40)->nullable()->change();
            });
        }
    }
};
