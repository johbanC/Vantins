<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    /** The application starts from a client record; everything else is filled in afterwards. */
    protected function handleRecordCreation(array $data): Model
    {
        return Application::createForClient(Client::findOrFail($data['client_id']), auth()->user());
    }
}
