<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Application::query()->with('creator')->chunkById(200, function ($applications) {
            foreach ($applications as $application) {
                $application->forceFill([
                    'agency_name' => config('vantins.agency_name'),
                    'agency_phone' => config('vantins.agency_phone'),
                    'contact_agent_name' => $application->contact_agent_name ?: $application->creator?->name,
                ])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // no-op
    }
};
