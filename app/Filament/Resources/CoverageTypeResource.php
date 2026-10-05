<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CoverageTypeResource\Pages;
use App\Models\CoverageType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The coverage catalog: admins rename coverages, edit the amounts offered and switch them on or off.
 * New coverage kinds need code (their fields), so they are not created here.
 */
class CoverageTypeResource extends Resource
{
    protected static ?string $model = CoverageType::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'name_en';

    public static function getModelLabel(): string
    {
        return __('panel.catalog.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.catalog.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Amounts as a list of numbers: a TagsInput that only keeps real, positive, unique amounts. */
    protected static function amounts(string $name, string $field): Forms\Components\TagsInput
    {
        return Forms\Components\TagsInput::make($name)
            ->label(__('panel.catalog.'.$name))
            ->helperText(__('panel.catalog.amounts_hint'))
            ->placeholder('1000000')
            // "$50,000" and "50000" are both fine; anything that is not a positive amount is not.
            ->nestedRecursiveRules([fn () => function (string $attribute, $value, \Closure $fail) {
                $amount = str_replace([',', '$', ' '], '', (string) $value);

                if (! is_numeric($amount) || (float) $amount <= 0) {
                    $fail(__('app.validation.coverage_amount'));
                }
            }])
            ->dehydrateStateUsing(fn ($state) => collect($state)
                ->map(fn ($v) => (float) str_replace([',', '$', ' '], '', (string) $v))
                ->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all())
            ->visible(fn (?CoverageType $record) => $record?->hasField($field) ?? false)
            ->columnSpanFull();
    }

    public static function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('name_en')->label(__('panel.catalog.name_en'))->required()->maxLength(255),
            Forms\Components\TextInput::make('name_es')->label(__('panel.catalog.name_es'))->required()->maxLength(255),
            Forms\Components\Toggle::make('is_active')->label(__('panel.catalog.is_active')),
            Forms\Components\TextInput::make('sort_order')->label(__('panel.catalog.sort_order'))->numeric()->minValue(0),
            static::amounts('limit_options', 'limit'),
            static::amounts('aggregate_options', 'aggregate'),
            static::amounts('deductible_options', 'deductible'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_en')->label(__('panel.catalog.name_en')),
                Tables\Columns\TextColumn::make('name_es')->label(__('panel.catalog.name_es')),
                Tables\Columns\TextColumn::make('limit_options')
                    ->label(__('panel.catalog.limit_options'))
                    ->state(fn (CoverageType $r) => static::amountList($r->options('limit'), $r->hasField('limit'))),
                Tables\Columns\TextColumn::make('deductible_options')
                    ->label(__('panel.catalog.deductible_options'))
                    ->state(fn (CoverageType $r) => static::amountList($r->options('deductible'), $r->hasField('deductible'))),
                Tables\Columns\IconColumn::make('is_active')->label(__('panel.catalog.is_active'))->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    /** @param  list<int|float>  $amounts */
    protected static function amountList(array $amounts, bool $applies): string
    {
        if (! $applies) {
            return '—';
        }

        return $amounts === [] ? __('panel.catalog.free_amount') : collect($amounts)->map(fn ($a) => '$'.number_format($a))->implode(' · ');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoverageTypes::route('/'),
            'edit' => Pages\EditCoverageType::route('/{record}/edit'),
        ];
    }
}
