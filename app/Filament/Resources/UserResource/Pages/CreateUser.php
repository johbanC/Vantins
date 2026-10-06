<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** No password typed: the person is invited to choose their own. */
    protected bool $invite = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->invite = blank($data['password'] ?? null);
        $data['locale'] ??= app()->getLocale();

        if ($this->invite) {
            // The column cannot be empty; nobody knows this value, the invitation replaces it.
            $data['password'] = Str::random(40);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->invite) {
            $this->record->forceFill(['password_set_at' => now()])->saveQuietly();

            return;
        }

        UserResource::sendInvitation($this->record);
    }
}
