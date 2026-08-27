<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicationResource\Pages;
use App\Filament\Resources\ApplicationResource\RelationManagers;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function getModelLabel(): string
    {
        return __('panel.resource.application');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resource.applications');
    }

    protected static function statusOptions(): array
    {
        return collect(Application::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => __('panel.status.'.$s)])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Shown only when creating: the minimum to identify the application.
            Forms\Components\Section::make(__('panel.section.client'))
                ->description(__('panel.section.client_hint'))
                ->columns(2)
                ->visibleOn('create')
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label(__('panel.field.company_name'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label(__('panel.field.email'))
                        ->email()
                        ->maxLength(255),
                ]),

            // Everything below is only relevant once the application exists.
            Forms\Components\Section::make(__('panel.section.applicant'))
                ->description(__('panel.section.schedules_hint'))
                ->columns(2)
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\TextInput::make('company_name')->label(__('panel.field.company_name')),
                    Forms\Components\TextInput::make('company_representative')->label(__('panel.field.company_representative')),
                    Forms\Components\TextInput::make('phone_number')->label(__('panel.field.phone_number'))->tel(),
                    Forms\Components\TextInput::make('email')->label(__('panel.field.email'))->email(),
                    Forms\Components\TextInput::make('mailing_address')->label(__('panel.field.mailing_address')),
                    Forms\Components\TextInput::make('parking_address')->label(__('panel.field.parking_address')),
                    Forms\Components\DatePicker::make('effective_date')->label(__('panel.field.effective_date'))->native(false),
                    Forms\Components\TextInput::make('us_dot_number')->label(__('panel.field.us_dot_number')),
                    Forms\Components\TextInput::make('radius_of_operations')->label(__('panel.field.radius_of_operations')),
                    Forms\Components\TextInput::make('years_in_business')->label(__('panel.field.years_in_business')),
                    Forms\Components\TextInput::make('power_units')->label(__('panel.field.power_units'))->numeric(),
                    Forms\Components\Textarea::make('commodities_hauled')->label(__('panel.field.commodities_hauled'))->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('panel.section.finance_agency'))
                ->columns(2)
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\TextInput::make('total_policy_premium')->label(__('panel.field.total_policy_premium'))->numeric()->prefix('$'),
                    Forms\Components\TextInput::make('agency_name')->label(__('panel.field.agency_name')),
                    Forms\Components\TextInput::make('agency_phone')->label(__('panel.field.agency_phone'))->tel(),
                    Forms\Components\TextInput::make('contact_agent_name')->label(__('panel.field.contact_agent_name')),
                ]),

            Forms\Components\Section::make(__('panel.section.disclosure'))
                ->description(__('panel.section.disclosure_hint'))
                ->columns(2)
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Placeholder::make('signer_name')
                        ->label(__('panel.field.signer_name'))
                        ->content(fn (?Application $record) => $record?->signer_name ?: '—'),
                    Forms\Components\Placeholder::make('disclosure_accepted_at')
                        ->label(__('panel.field.disclosure_accepted_at'))
                        ->content(fn (?Application $record) => optional($record?->disclosure_accepted_at)?->format('Y-m-d H:i') ?: '—'),
                    Forms\Components\Placeholder::make('signature')
                        ->label(__('panel.field.signature'))
                        ->columnSpanFull()
                        ->content(function (?Application $record) {
                            if ($record?->signature_path && Storage::disk('public')->exists($record->signature_path)) {
                                $data = base64_encode(Storage::disk('public')->get($record->signature_path));

                                return new HtmlString('<img src="data:image/png;base64,'.$data.'" style="max-height:8rem" class="rounded border border-gray-300 bg-white p-1">');
                            }

                            return __('panel.field.not_signed');
                        }),
                    Forms\Components\Placeholder::make('locale')
                        ->label(__('panel.field.locale'))
                        ->content(fn (?Application $record) => match ($record?->locale) {
                            'es' => 'Español', 'en' => 'English', default => '—',
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label(__('panel.field.company_name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Application $r) => $r->company_representative),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('panel.status.label'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('panel.status.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'in_review' => 'warning',
                        'quoted' => 'primary',
                        'signed', 'issued' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')->label(__('panel.field.created_by'))->toggleable(),
                Tables\Columns\TextColumn::make('email')->label(__('panel.field.email'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('us_dot_number')->label(__('panel.field.us_dot_number'))->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('total_policy_premium')->label(__('panel.field.total_policy_premium'))->money('USD')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->label(__('panel.field.submitted_at'))->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('panel.field.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('panel.status.label'))
                    ->options(static::statusOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('changeStatus')
                    ->label(__('panel.action.change_status'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label(__('panel.status.label'))
                            ->options(static::statusOptions())
                            ->default(fn (Application $record) => $record->status)
                            ->required()
                            ->native(false),
                    ])
                    ->action(fn (Application $record, array $data) => $record->markStatus($data['status'])),
                Tables\Actions\Action::make('copyLink')
                    ->label(__('panel.action.client_link'))
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading(__('panel.action.client_link_heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-actions::modal.actions.cancel.label'))
                    ->modalContent(fn (Application $record) => view('filament.application-link', [
                        'url' => route('apply.show', $record->token),
                    ])),
                Tables\Actions\Action::make('pdf')
                    ->label(__('panel.action.pdf'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn (Application $record) => route('applications.pdf', $record->token))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DriversRelationManager::class,
            RelationManagers\VehiclesRelationManager::class,
            RelationManagers\TrailersRelationManager::class,
            RelationManagers\CoveragesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }
}
