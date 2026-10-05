<?php

namespace App\Filament\Resources\QuoteResource\RelationManagers;

use App\Models\QuoteStageChange;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Who moved the quote to which stage, and when. Read-only. */
class StageChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'stageChanges';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.quote.history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('panel.quote.history_when'))
                    ->formatStateUsing(fn ($state) => Format::dateTime($state)),
                Tables\Columns\TextColumn::make('user_name')
                    ->label(__('panel.quote.history_who'))
                    ->state(fn (QuoteStageChange $r) => $r->user_name ?: __('panel.quote.no_one')),
                Tables\Columns\TextColumn::make('from_stage')
                    ->label(__('panel.quote.history_from'))
                    ->formatStateUsing(fn (?string $state) => $state ? __('panel.quote.stages.'.$state) : '—'),
                Tables\Columns\TextColumn::make('to_stage')
                    ->label(__('panel.quote.history_to'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('panel.quote.stages.'.$state)),
                Tables\Columns\TextColumn::make('note')->label(__('panel.quote.history_note'))->wrap()->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25]);
    }
}
