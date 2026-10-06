<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Rules\ValidVin;
use App\Support\DataQuality;
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
            Forms\Components\TextInput::make('year')->label(__('app.year'))->extraInputAttributes(['maxlength' => 4, 'inputmode' => 'numeric'])
                ->rules(['integer', 'between:1950,'.(now()->year + 1)])
                ->live(onBlur: true)
                ->validationMessages(['between' => __('app.validation.year_range', ['min' => 1950, 'max' => now()->year + 1])]),
            Forms\Components\TextInput::make('make')->label(__('app.make'))->maxLength(190),
            Forms\Components\TextInput::make('vin')->label(__('app.vin'))->maxLength(17)
                ->rules(fn (Forms\Get $get) => [new ValidVin($get('year'))]),
            Forms\Components\TextInput::make('body_type')->label(__('app.body_type'))->maxLength(190),
            Forms\Components\TextInput::make('garaging_zip')->label(__('app.garaging_zip'))
                ->maxLength(10)
                ->regex('/^\d{5}(-\d{4})?$/')
                ->validationMessages(['regex' => __('app.validation.zip')]),
            Forms\Components\TextInput::make('stated_value')->label(__('app.stated_value'))->numeric()->prefix('$')->minValue(0),

            Forms\Components\Section::make(__('app.has_physical_damage'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Toggle::make('has_physical_damage')
                        ->label(__('app.has_physical_damage'))
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('physical_damage_value')->label(__('app.physical_damage_value'))
                        ->numeric()->prefix('$')->gt(0)
                        ->visible(fn (Forms\Get $get) => (bool) $get('has_physical_damage'))
                        ->required(fn (Forms\Get $get) => (bool) $get('has_physical_damage')),
                    Forms\Components\TextInput::make('physical_damage_deductible')->label(__('app.physical_damage_deductible'))
                        ->numeric()->prefix('$')->gt(0)
                        ->helperText(__('app.warning.pd_deductible_range', [
                            'min' => number_format(DataQuality::PD_DEDUCTIBLE_MIN), 'max' => number_format(DataQuality::PD_DEDUCTIBLE_MAX),
                        ]))
                        ->visible(fn (Forms\Get $get) => (bool) $get('has_physical_damage'))
                        ->required(fn (Forms\Get $get) => (bool) $get('has_physical_damage')),
                ]),
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
                Tables\Columns\TextColumn::make('garaging_zip')->label(__('app.garaging_zip')),
                Tables\Columns\TextColumn::make('stated_value')->label(__('app.stated_value'))->money('USD'),
                Tables\Columns\IconColumn::make('has_physical_damage')->label(__('app.has_physical_damage'))->boolean(),
                Tables\Columns\TextColumn::make('physical_damage_value')->label(__('app.physical_damage_value'))->money('USD')->toggleable(),
                Tables\Columns\TextColumn::make('physical_damage_deductible')->label(__('app.physical_damage_deductible'))->money('USD')->toggleable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label(__('app.add'))])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
