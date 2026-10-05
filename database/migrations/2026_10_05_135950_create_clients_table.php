<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('phone_normalized', 20)->nullable()->index(); // digits only, no +1
            $table->string('us_dot_number', 20)->nullable()->index();
            $table->string('mc_number', 20)->nullable()->index();
            $table->string('mailing_address')->nullable();
            $table->string('parking_address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->nullOnDelete();
        });

        $this->backfillFromApplications();
    }

    /**
     * Every existing application becomes (or joins) a client. Applications that share a
     * US DOT number, an e-mail or a phone number are grouped under the same client.
     */
    protected function backfillFromApplications(): void
    {
        foreach (DB::table('applications')->orderBy('id')->get() as $app) {
            $phone = Client::normalizePhone($app->phone_number);
            $email = $app->email ? mb_strtolower(trim($app->email)) : null;
            $dot = Client::digits($app->us_dot_number);

            $existing = DB::table('clients')
                ->where(function ($q) use ($dot, $email, $phone) {
                    $q->whereRaw('1 = 0');
                    $dot && $q->orWhere('us_dot_number', $dot);
                    $email && $q->orWhere('email', $email);
                    $phone && $q->orWhere('phone_normalized', $phone);
                })
                ->value('id');

            $clientId = $existing ?? DB::table('clients')->insertGetId([
                'company_name' => $app->company_name ?: 'Sin nombre',
                'contact_name' => $app->company_representative,
                'email' => $email,
                'phone' => $app->phone_number,
                'phone_normalized' => $phone,
                'us_dot_number' => $dot,
                'mailing_address' => $app->mailing_address,
                'parking_address' => $app->parking_address,
                'assigned_user_id' => $app->created_by,
                'created_by' => $app->created_by,
                'is_demo' => (bool) $app->is_demo,
                'created_at' => $app->created_at,
                'updated_at' => now(),
            ]);

            DB::table('applications')->where('id', $app->id)->update(['client_id' => $clientId]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::dropIfExists('clients');
    }
};
