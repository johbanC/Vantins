<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history: who did what, to which record, and when.
        // No foreign keys on the records on purpose: the history outlives them.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();          // kept even if the user is removed
            $table->string('event', 40);                      // created, updated, deleted, status_changed
            $table->string('subject_type', 60);               // application, client, driver, ...
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->json('changes')->nullable();             // {field: [old, new]}
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
