<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QuoteResource::moveAction(Actions\Action::class),
            ...QuoteResource::linkActions(Actions\Action::class),
            QuoteResource::reviseAction(Actions\Action::class),
            Actions\EditAction::make(),
        ];
    }
}
