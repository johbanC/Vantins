<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarrierResource\Pages;
use App\Models\Carrier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** The insurance companies we quote with. Internal: clients never see these names. */
class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('panel.quote.carrier_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.quote.carrier_plural');
    }

    /** Also used by the "create carrier" modal inside the quote form. */
    public static function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label(__('panel.quote.carrier'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('is_active')->label(__('panel.quote.carrier_active'))->default(true),
            Forms\Components\Textarea::make('notes')->label(__('panel.quote.carrier_notes'))->rows(3),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('panel.quote.carrier'))->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('panel.quote.carrier_active'))->boolean(),
                Tables\Columns\TextColumn::make('quotes_count')->label(__('panel.quote.plural'))->counts('quotes')->sortable(),
            ])
            ->defaultSort('name')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCarriers::route('/'),
        ];
    }
}
