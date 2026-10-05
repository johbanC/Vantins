<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // The client's link: it expires, it can be revoked, and driver data sits behind a PIN.
            $table->timestamp('link_expires_at')->nullable()->after('token');
            $table->timestamp('link_revoked_at')->nullable()->after('link_expires_at');
            $table->text('link_pin')->nullable()->after('link_revoked_at');   // encrypted, staff can read it back

            // A signed application never changes: a correction is a new version of it.
            $table->unsignedInteger('revision')->default(1)->after('status');
            $table->foreignId('revision_of_id')->nullable()->after('revision')->constrained('applications')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('revision_of_id');
            $table->text('revision_reason')->nullable()->after('superseded_at');
        });

        // Links that already exist get a PIN and, if still waiting for the client, 30 more days.
        foreach (DB::table('applications')->get(['id', 'signed_at', 'status']) as $application) {
            DB::table('applications')->where('id', $application->id)->update([
                'link_pin' => Crypt::encryptString((string) random_int(100000, 999999)),
                'link_expires_at' => $application->signed_at || $application->status === 'cancelled' ? null : now()->addDays(30),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn(['link_expires_at', 'link_revoked_at', 'link_pin', 'revision', 'superseded_at', 'revision_reason']);
        });
    }
};
