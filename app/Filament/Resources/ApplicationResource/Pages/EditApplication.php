<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fill')
                ->label(__('panel.action.fill'))
                ->tooltip(__('panel.action.fill_tooltip'))
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(fn () => route('apply.show', $this->record->token))
                ->openUrlInNewTab(),
            Actions\Action::make('changeStatus')
                ->label(__('panel.action.change_status'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label(__('panel.status.label'))
                        ->options(collect(Application::STATUSES)->mapWithKeys(fn ($s) => [$s => __('panel.status.'.$s)])->all())
                        ->default(fn () => $this->record->status)
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data) {
                    $this->record->markStatus($data['status']);
                    $this->refreshFormData(['status']);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
