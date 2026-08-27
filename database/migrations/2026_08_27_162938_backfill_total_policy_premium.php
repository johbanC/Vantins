<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Application::query()->chunkById(200, function ($applications) {
            foreach ($applications as $application) {
                $application->recalculatePremium();
            }
        });
    }

    public function down(): void
    {
        // no-op: the value is derived and kept in sync by the Coverage model.
    }
};
