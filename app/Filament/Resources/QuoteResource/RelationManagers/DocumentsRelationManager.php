<?php

namespace App\Filament\Resources\QuoteResource\RelationManagers;

use App\Models\QuoteDocument;
use App\Support\Format;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Proposals, signed acceptance, binder and receipts. Private files, downloaded through a checked route. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.quote.documents');
    }

    /** Documents are worked on from the view page too, so read-only depends on the permission, not the page. */
    public function isReadOnly(): bool
    {
        return ! auth()->user()->can('manage', $this->getOwnerRecord());
    }

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\Select::make('type')
                ->label(__('panel.quote.document_type'))
                ->options(collect(QuoteDocument::TYPES)->mapWithKeys(fn ($t) => [$t => __('panel.quote.document_types.'.$t)])->all())
                ->required()->native(false),
            Forms\Components\TextInput::make('name')->label(__('panel.quote.document_name'))->maxLength(255),
            Forms\Components\FileUpload::make('path')
                ->label(__('panel.quote.document_file'))
                ->helperText(__('panel.quote.document_hint'))
                ->disk(QuoteDocument::DISK)
                ->directory('quote-documents')
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->storeFileNamesIn('name')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('panel.quote.document_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('panel.quote.document_types.'.$state)),
                Tables\Columns\TextColumn::make('name')->label(__('panel.quote.document_name')),
                Tables\Columns\TextColumn::make('uploader.name')->label(__('panel.quote.document_by')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('panel.quote.history_when'))
                    ->formatStateUsing(fn ($state) => Format::dateTime($state)),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('app.add'))
                    ->mutateFormDataUsing(fn (array $data) => $data + ['uploaded_by' => auth()->id()]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label(__('panel.quote.document_download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (QuoteDocument $record) => route('quote-documents.download', $record)),
            ])
            ->bulkActions([]);
    }
}
