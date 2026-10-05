<?php

namespace App\Filament\RelationManagers;

use App\Models\ActivityLog;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only history of a record (application or client): who changed what and when.
 */
class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activity';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.audit.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    /** @return list<Tables\Columns\Column> */
    public static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->label(__('panel.audit.when'))
                ->formatStateUsing(fn ($state) => Format::dateTime($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('user_name')
                ->label(__('panel.audit.who'))
                ->state(fn (ActivityLog $r) => $r->actorName()),
            Tables\Columns\TextColumn::make('event')
                ->label(__('panel.audit.event'))
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'created' => 'success',
                    'deleted' => 'danger',
                    'status_changed' => 'warning',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state) => __('panel.audit.events.'.$state)),
            Tables\Columns\TextColumn::make('subject_label')
                ->label(__('panel.audit.record'))
                ->state(fn (ActivityLog $r) => $r->subjectLabel())
                ->wrap(),
            Tables\Columns\TextColumn::make('changes')
                ->label(__('panel.audit.detail'))
                ->state(fn (ActivityLog $r) => $r->summaryLines())
                ->listWithLineBreaks()
                ->wrap(),
        ];
    }
}
