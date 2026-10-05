<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Search with the whole text: "(754) 290-0308" or "Rodriguez Freight" must not be
     * split into separate words, Client::search() handles phones and names itself.
     *
     * @return array<string>
     */
    protected function extractTableSearchWords(string $search): array
    {
        $search = trim($search);

        return $search === '' ? [] : [$search];
    }
}
