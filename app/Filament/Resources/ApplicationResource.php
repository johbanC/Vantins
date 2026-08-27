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

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Estado / Status')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(collect(Application::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst(str_replace('_', ' ', $s))]))
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('locale')
                        ->label('Idioma')
                        ->options(['en' => 'English', 'es' => 'Español'])
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('token')
                        ->label('Client link token')
                        ->disabled()
                        ->dehydrated(false),
                ]),

            Forms\Components\Section::make('Applicant Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('company_name'),
                    Forms\Components\TextInput::make('company_representative'),
                    Forms\Components\TextInput::make('phone_number')->tel(),
                    Forms\Components\TextInput::make('email')->email(),
                    Forms\Components\TextInput::make('mailing_address'),
                    Forms\Components\TextInput::make('parking_address'),
                    Forms\Components\DatePicker::make('effective_date')->native(false),
                    Forms\Components\TextInput::make('us_dot_number')->label('US DOT #'),
                    Forms\Components\TextInput::make('radius_of_operations'),
                    Forms\Components\TextInput::make('years_in_business'),
                    Forms\Components\TextInput::make('power_units')->numeric(),
                    Forms\Components\Textarea::make('commodities_hauled')->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Finance Proposal & Agency')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('total_policy_premium')->numeric()->prefix('$'),
                    Forms\Components\TextInput::make('agency_name'),
                    Forms\Components\TextInput::make('agency_phone')->tel(),
                    Forms\Components\TextInput::make('contact_agent_name'),
                ]),

            Forms\Components\Section::make('Disclosure & Signature')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('signer_name'),
                    Forms\Components\DateTimePicker::make('disclosure_accepted_at')->native(false),
                    Forms\Components\FileUpload::make('signature_path')
                        ->image()
                        ->directory('signatures')
                        ->disk('public')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Application $r) => $r->company_representative),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'in_review' => 'warning',
                        'quoted' => 'primary',
                        'signed' => 'success',
                        'issued' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')->label('Lang')->badge(),
                Tables\Columns\TextColumn::make('creator.name')->label('Created by')->toggleable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('us_dot_number')->label('US DOT')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('total_policy_premium')->money('USD')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(Application::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst(str_replace('_', ' ', $s))])),
                Tables\Filters\SelectFilter::make('locale')
                    ->options(['en' => 'English', 'es' => 'Español']),
            ])
            ->actions([
                Tables\Actions\Action::make('copyLink')
                    ->label('Client link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('Link para el cliente')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (Application $record) => view('filament.application-link', [
                        'url' => route('apply.show', $record->token),
                    ])),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
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
