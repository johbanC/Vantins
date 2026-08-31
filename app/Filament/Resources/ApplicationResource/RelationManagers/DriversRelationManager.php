<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Filament\Resources\ApplicationResource\RelationManagers\Concerns\LocksWithApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DriversRelationManager extends RelationManager
{
    use LocksWithApplication;

    protected static string $relationship = 'drivers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.drivers_schedule');
    }

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('driver_name')->label(__('app.driver_name'))->required(),
            Forms\Components\DatePicker::make('dob')->label(__('app.dob'))->native(false),
            Forms\Components\TextInput::make('cdl_number')->label(__('app.cdl_number')),
            Forms\Components\TextInput::make('state_issued')->label(__('app.state_issued')),
            Forms\Components\TextInput::make('experience')->label(__('app.experience')),
            Forms\Components\DatePicker::make('date_of_hire')->label(__('app.date_of_hire'))->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        $unlocked = fn (): bool => ! $this->isApplicationLocked();

        return $table
            ->recordTitleAttribute('driver_name')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('driver_name')->label(__('app.driver_name')),
                Tables\Columns\TextColumn::make('dob')->label(__('app.dob'))->date(),
                Tables\Columns\TextColumn::make('cdl_number')->label(__('app.cdl_number')),
                Tables\Columns\TextColumn::make('state_issued')->label(__('app.state_issued')),
                Tables\Columns\TextColumn::make('experience')->label(__('app.experience')),
                Tables\Columns\TextColumn::make('date_of_hire')->label(__('app.date_of_hire'))->date(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label(__('app.add'))->visible($unlocked)])
            ->actions([
                Tables\Actions\EditAction::make()->visible($unlocked),
                Tables\Actions\DeleteAction::make()->visible($unlocked),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible($unlocked),
            ]);
    }
}
