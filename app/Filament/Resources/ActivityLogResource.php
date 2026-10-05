<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Every change in the system, newest first. Admins only; nothing can be edited or removed. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('panel.audit.resource');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.audit.resource');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(ActivityRelationManager::columns())
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('panel.audit.user_filter'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('event')
                    ->label(__('panel.audit.event_filter'))
                    ->options(collect(['created', 'updated', 'deleted', 'status_changed'])
                        ->mapWithKeys(fn ($e) => [$e => __('panel.audit.events.'.$e)])->all()),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label(__('panel.audit.type_filter'))
                    ->options(collect(['application', 'client', 'driver', 'vehicle', 'trailer', 'coverage', 'user'])
                        ->mapWithKeys(fn ($t) => [$t => __('panel.audit.subjects.'.$t)])->all()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
