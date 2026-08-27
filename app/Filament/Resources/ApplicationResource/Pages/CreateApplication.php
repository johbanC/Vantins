<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] ??= 'created';
        $data['locale'] ??= 'en';

        // Agency block is fixed: Vantins + the advisor creating the application.
        $data['agency_name'] = config('vantins.agency_name');
        $data['agency_phone'] = config('vantins.agency_phone');
        $data['contact_agent_name'] ??= auth()->user()?->name;

        return $data;
    }
}
