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

    public function getSubheading(): ?string
    {
        return $this->record->isLocked() ? __('panel.locked_notice') : null;
    }

    /** A signed / issued application is frozen: no Save button. */
    protected function getFormActions(): array
    {
        return $this->record->isLocked() ? [] : parent::getFormActions();
    }

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
            Actions\Action::make('pdf')
                ->label(__('panel.action.pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->disabled(fn () => ! $this->record->canGeneratePdf())
                ->tooltip(fn () => $this->record->canGeneratePdf() ? null : __('panel.action.pdf_disabled_hint'))
                ->extraAttributes(['style' => 'pointer-events: auto'])
                ->modalHeading(__('panel.action.pdf_heading'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament-actions::modal.actions.cancel.label'))
                ->modalContent(fn () => view('filament.application-pdf', [
                    'en' => route('applications.pdf', ['token' => $this->record->token, 'locale' => 'en']),
                    'es' => route('applications.pdf', ['token' => $this->record->token, 'locale' => 'es']),
                ])),
            Actions\Action::make('welcomeLetter')
                ->label(__('panel.action.welcome_letter'))
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->disabled(fn () => ! $this->record->canSendWelcomeLetter())
                ->tooltip(fn () => $this->record->canSendWelcomeLetter() ? null : __('panel.action.pdf_disabled_hint'))
                ->extraAttributes(['style' => 'pointer-events: auto'])
                ->modalHeading(__('panel.action.welcome_letter_heading'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament-actions::modal.actions.cancel.label'))
                ->modalContent(fn () => view('filament.welcome-letter', [
                    'en' => route('applications.welcome-letter', ['token' => $this->record->token, 'locale' => 'en']),
                    'es' => route('applications.welcome-letter', ['token' => $this->record->token, 'locale' => 'es']),
                    'sentAt' => $this->record->welcome_letter_sent_at,
                ])),
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
            Actions\DeleteAction::make()
                ->hidden(fn () => ! $this->record->isDeletable()),
        ];
    }
}
