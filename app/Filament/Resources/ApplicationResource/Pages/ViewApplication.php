<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApplicationResource::revisionAction(Actions\Action::class),
            ApplicationResource::changeStatusAction(Actions\Action::class),
            ApplicationResource::pdfAction(Actions\Action::class),
            ApplicationResource::copyLinkAction(Actions\Action::class),
            ApplicationResource::renewLinkAction(Actions\Action::class),
            ApplicationResource::revokeLinkAction(Actions\Action::class),
            Actions\EditAction::make(),
        ];
    }
}
