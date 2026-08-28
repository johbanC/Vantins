<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.vehicles_schedule');
    }

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('year')->label(__('app.year'))->maxLength(4),
            Forms\Components\TextInput::make('make')->label(__('app.make'))->maxLength(190),
            Forms\Components\TextInput::make('vin')->label(__('app.vin'))->maxLength(17),
            Forms\Components\TextInput::make('body_type')->label(__('app.body_type'))->maxLength(190),
            Forms\Components\TextInput::make('stated_value')->label(__('app.stated_value'))->numeric()->prefix('$'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vin')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('year')->label(__('app.year')),
                Tables\Columns\TextColumn::make('make')->label(__('app.make')),
                Tables\Columns\TextColumn::make('vin')->label(__('app.vin')),
                Tables\Columns\TextColumn::make('body_type')->label(__('app.body_type')),
                Tables\Columns\TextColumn::make('stated_value')->label(__('app.stated_value'))->money('USD'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label(__('app.add'))])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
