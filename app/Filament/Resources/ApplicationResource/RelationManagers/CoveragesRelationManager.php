<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Filament\Resources\ApplicationResource\RelationManagers\Concerns\LocksWithApplication;
use App\Models\Coverage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CoveragesRelationManager extends RelationManager
{
    use LocksWithApplication;

    protected static string $relationship = 'coverages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.coverages_list');
    }

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('coverage')->label(__('app.coverage'))->required(),
            Forms\Components\TextInput::make('limit_amount')->label(__('app.limit')),
            Forms\Components\TextInput::make('deductible')->label(__('app.deductible')),
        ]);
    }

    public function table(Table $table): Table
    {
        $unlocked = fn (): bool => ! $this->isApplicationLocked();

        return $table
            ->recordTitleAttribute('coverage')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('coverage')->label(__('app.coverage')),
                Tables\Columns\TextColumn::make('limit_amount')
                    ->label(__('app.limit'))
                    ->formatStateUsing(fn (?string $state) => Coverage::formatAmount($state)),
                Tables\Columns\TextColumn::make('deductible')
                    ->label(__('app.deductible'))
                    ->formatStateUsing(fn (?string $state) => Coverage::formatAmount($state)),
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
