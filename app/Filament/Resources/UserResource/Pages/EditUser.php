<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UserResource::resendInvitationAction(Actions\Action::class),
            UserResource::copyInvitationLinkAction(Actions\Action::class),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if (filled($this->data['password'] ?? null)) {
            $this->record->forceFill(['password_set_at' => now()])->saveQuietly();
        }
    }
}
