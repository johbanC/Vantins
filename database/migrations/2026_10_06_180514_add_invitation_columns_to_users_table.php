<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the last invitation went out, and when the person chose (or was given) a password.
            // A user with no password_set_at is still waiting to accept the invitation.
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('password_set_at')->nullable();
        });

        // Everyone that exists already has a working password.
        DB::table('users')->update(['password_set_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invited_at', 'password_set_at']);
        });
    }
};
