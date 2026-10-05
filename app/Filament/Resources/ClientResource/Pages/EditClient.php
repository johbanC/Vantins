<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A client with applications is history: it can never be deleted.
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->record->applications()->exists()),
        ];
    }
}
