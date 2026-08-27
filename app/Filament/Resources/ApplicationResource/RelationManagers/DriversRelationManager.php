<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DriversRelationManager extends RelationManager
{
    protected static string $relationship = 'drivers';

    protected static ?string $title = 'Driver Schedule';

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('driver_name')->required(),
            Forms\Components\DatePicker::make('dob')->label('DOB')->native(false),
            Forms\Components\TextInput::make('cdl_number')->label('CDL #'),
            Forms\Components\TextInput::make('state_issued')->label('State Iss.'),
            Forms\Components\TextInput::make('experience')->label('Experience (years)'),
            Forms\Components\DatePicker::make('date_of_hire')->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver_name')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('driver_name'),
                Tables\Columns\TextColumn::make('dob')->date(),
                Tables\Columns\TextColumn::make('cdl_number')->label('CDL #'),
                Tables\Columns\TextColumn::make('state_issued')->label('State'),
                Tables\Columns\TextColumn::make('experience'),
                Tables\Columns\TextColumn::make('date_of_hire')->date(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
