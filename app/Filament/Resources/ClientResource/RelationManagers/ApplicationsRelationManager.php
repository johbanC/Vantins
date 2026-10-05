<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.client.applications');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('company_name')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label(__('panel.field.created_at'))->formatStateUsing(fn ($state) => Format::date($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('panel.status.label'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('panel.status.'.$state)),
                Tables\Columns\TextColumn::make('total_policy_premium')->label(__('panel.field.total_policy_premium'))->money('USD'),
                Tables\Columns\TextColumn::make('creator.name')->label(__('panel.field.created_by')),
                Tables\Columns\TextColumn::make('signed_at')->label(__('panel.field.signed_at'))->formatStateUsing(fn ($state) => Format::date($state)),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label(__('panel.client.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Application $record) => ApplicationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
