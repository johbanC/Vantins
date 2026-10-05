<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Models\Application;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Nobody quotes on an application they may not change.
        abort_unless(auth()->user()->can('manage', Application::findOrFail($data['application_id'])), 403);

        $data['stage'] = 'lead';
        $data['version'] = 1;
        $data['producer_id'] ??= auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return QuoteResource::getUrl('view', ['record' => $this->record]);
    }
}
