<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Models\Driver;
use App\Support\DataQuality;
use App\Support\Format;
use App\Support\Mask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DriversRelationManager extends RelationManager
{
    protected static string $relationship = 'drivers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.drivers_schedule');
    }

    /** Full date of birth and CDL number: admins and whoever created the application. */
    protected function canRevealSensitive(): bool
    {
        return $this->getOwnerRecord()->canRevealSensitiveData(auth()->user());
    }

    public function form(Form $form): Form
    {
        $minAge = DataQuality::MIN_DRIVER_AGE;

        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('driver_name')->label(__('app.driver_name'))->required()->maxLength(190),
            Forms\Components\DatePicker::make('dob')->label(__('app.dob'))->native(false)
                ->visible(fn () => $this->canRevealSensitive())
                ->minDate('1920-01-01')
                ->maxDate(now()->subYears($minAge))
                ->validationMessages([
                    'before_or_equal' => __('app.validation.dob_min_age', ['age' => $minAge]),
                    'after_or_equal' => __('app.validation.date_implausible'),
                ]),
            Forms\Components\TextInput::make('cdl_number')->label(__('app.cdl_number'))
                ->visible(fn () => $this->canRevealSensitive())
                ->minLength(4)->maxLength(25)
                ->regex('/^[A-Za-z0-9\- ]+$/')
                ->validationMessages([
                    'regex' => __('app.validation.cdl_chars'),
                    'min' => __('app.validation.cdl_length'),
                ]),
            Forms\Components\TextInput::make('state_issued')->label(__('app.state_issued'))->maxLength(40),
            Forms\Components\DatePicker::make('cdl_issue_date')->label(__('app.cdl_issue_date'))->native(false)
                ->minDate(fn (Forms\Get $get) => $get('dob') ? Carbon::parse($get('dob'))->addYears($minAge) : '1950-01-01')
                ->maxDate(now())
                ->live()
                ->validationMessages([
                    'before_or_equal' => __('app.validation.date_future'),
                    'after_or_equal' => __('app.validation.cdl_issue_before_18'),
                ]),
            Forms\Components\DatePicker::make('cdl_expiry_date')->label(__('app.cdl_expiry_date'))->native(false)
                ->minDate(fn (Forms\Get $get) => $get('cdl_issue_date') ? Carbon::parse($get('cdl_issue_date'))->addDay() : '1990-01-01')
                ->maxDate(now()->addYears(20))
                ->validationMessages([
                    'before_or_equal' => __('app.validation.cdl_expiry_too_far'),
                    'after_or_equal' => __('app.validation.cdl_expiry_before_issue'),
                ]),
            Forms\Components\TextInput::make('experience')->label(__('app.experience'))->maxLength(60),
            Forms\Components\DatePicker::make('date_of_hire')->label(__('app.date_of_hire'))->native(false)
                ->minDate(fn (Forms\Get $get) => $get('dob') ?: '1950-01-01')
                ->maxDate(now()->addYear())
                ->validationMessages([
                    'before_or_equal' => __('app.validation.date_future'),
                    'after_or_equal' => __('app.validation.hire_before_birth'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver_name')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('driver_name')->label(__('app.driver_name')),
                Tables\Columns\TextColumn::make('dob')->label(__('app.dob'))->formatStateUsing(fn ($state) => $this->canRevealSensitive() ? Format::date($state) : Mask::dob($state)),
                Tables\Columns\TextColumn::make('cdl_number')->label(__('app.cdl_number'))->formatStateUsing(fn ($state) => $this->canRevealSensitive() ? $state : Mask::cdl($state)),
                Tables\Columns\TextColumn::make('state_issued')->label(__('app.state_issued')),
                Tables\Columns\TextColumn::make('cdl_issue_date')->label(__('app.cdl_issue_date'))->formatStateUsing(fn ($state) => Format::date($state)),
                Tables\Columns\TextColumn::make('cdl_expiry_date')->label(__('app.cdl_expiry_date'))
                    ->formatStateUsing(fn ($state) => Format::date($state))
                    ->color(fn (Driver $record) => $record->cdl_expiry_date?->isPast() ? 'danger' : null)
                    ->icon(fn (Driver $record) => DataQuality::driverWarnings($record->toArray()) ? 'heroicon-o-exclamation-triangle' : null)
                    ->tooltip(fn (Driver $record) => implode("\n", DataQuality::driverWarnings($record->toArray())) ?: null),
                Tables\Columns\TextColumn::make('experience')->label(__('app.experience')),
                Tables\Columns\TextColumn::make('date_of_hire')->label(__('app.date_of_hire'))->formatStateUsing(fn ($state) => Format::date($state)),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label(__('app.add'))])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
