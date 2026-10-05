<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Filament\Resources\QuoteResource;
use App\Models\Application;
use App\Models\Quote;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The quotes of an application, one per carrier / alternative. Work on them happens on the quote's own page. */
class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.quote.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('carrier.name')
                    ->label(__('panel.quote.carrier'))
                    ->description(fn (Quote $r) => 'v'.$r->version.($r->isCurrent() ? '' : ' · '.__('panel.quote.replaced'))),
                Tables\Columns\TextColumn::make('stage')
                    ->label(__('panel.quote.stage'))
                    ->badge()
                    ->color(fn (string $state) => QuoteResource::stageColor($state))
                    ->formatStateUsing(fn (string $state) => __('panel.quote.stages.'.$state)),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label(__('panel.quote.total_cost'))
                    ->state(fn (Quote $r) => Format::money($r->totalCost())),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('panel.quote.expires_at'))
                    ->formatStateUsing(fn ($state) => Format::date($state)),
                Tables\Columns\IconColumn::make('selected')
                    ->label(__('panel.quote.sent_at'))
                    ->state(fn (Quote $r) => $r->application->selected_quote_id === $r->id)
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-airplane')
                    ->falseIcon(''),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['carrier', 'application']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('panel.quote.no_quotes'))
            ->headerActions([
                Tables\Actions\Action::make('newQuote')
                    ->label(__('panel.quote.new_quote'))
                    ->icon('heroicon-o-plus')
                    ->url(fn () => QuoteResource::getUrl('create').'?application='.$this->getOwnerRecord()->getKey())
                    ->visible(fn () => auth()->user()->can('create', Quote::class) && auth()->user()->can('manage', $this->getOwnerRecord())),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label(__('panel.quote.open_quote'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Quote $record) => QuoteResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
