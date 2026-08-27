<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    protected static ?string $title = 'Vehicle Schedule';

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('year')->maxLength(4),
            Forms\Components\TextInput::make('make'),
            Forms\Components\TextInput::make('vin')->label('VIN #'),
            Forms\Components\TextInput::make('body_type'),
            Forms\Components\TextInput::make('stated_value')->numeric()->prefix('$'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vin')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('year'),
                Tables\Columns\TextColumn::make('make'),
                Tables\Columns\TextColumn::make('vin')->label('VIN #'),
                Tables\Columns\TextColumn::make('body_type'),
                Tables\Columns\TextColumn::make('stated_value')->money('USD'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
