<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CoveragesRelationManager extends RelationManager
{
    protected static string $relationship = 'coverages';

    protected static ?string $title = 'Coverages List';

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('coverage')->required(),
            Forms\Components\TextInput::make('limit_amount')->label('Limit'),
            Forms\Components\TextInput::make('deductible'),
            Forms\Components\TextInput::make('premium')->numeric()->prefix('$'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('coverage')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('coverage'),
                Tables\Columns\TextColumn::make('limit_amount')->label('Limit'),
                Tables\Columns\TextColumn::make('deductible'),
                Tables\Columns\TextColumn::make('premium')->money('USD'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
