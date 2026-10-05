<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('panel.resource.client');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resource.clients');
    }

    /** An agent only sees their own clients; admins and read-only users see all. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    /** Shared by the Clients page and the "create client" modal inside the application form. */
    public static function formSchema(): array
    {
        return [
            Forms\Components\Placeholder::make('duplicate_warning')
                ->hiddenLabel()
                ->columnSpanFull()
                ->visible(fn (Forms\Get $get, ?Client $record) => static::duplicatesFor($get, $record)->isNotEmpty())
                ->content(fn (Forms\Get $get, ?Client $record) => static::duplicateWarning(static::duplicatesFor($get, $record))),

            Forms\Components\Section::make(__('panel.client.section_company'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('company_name')->label(__('panel.field.company_name'))->required()->maxLength(255),
                    Forms\Components\TextInput::make('contact_name')->label(__('panel.client.contact_name'))->maxLength(255),
                    Forms\Components\TextInput::make('email')->label(__('panel.field.email'))->email()->maxLength(255)->live(onBlur: true),
                    Forms\Components\TextInput::make('phone')->label(__('panel.field.phone_number'))->tel()->maxLength(40)->live(onBlur: true),
                    Forms\Components\TextInput::make('us_dot_number')->label(__('panel.field.us_dot_number'))->maxLength(20)->live(onBlur: true),
                    Forms\Components\TextInput::make('mc_number')->label(__('panel.client.mc_number'))->maxLength(20)->live(onBlur: true),
                    Forms\Components\TextInput::make('mailing_address')->label(__('panel.field.mailing_address'))->maxLength(255),
                    Forms\Components\TextInput::make('parking_address')->label(__('panel.field.parking_address'))->maxLength(255),
                ]),

            Forms\Components\Section::make(__('panel.client.section_assignment'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('assigned_user_id')
                        ->label(__('panel.client.assigned_user'))
                        // Plain options, not a relationship select: this form also opens inside the "create client" modal
                        // of the new-application form, where there is no record to resolve a relationship on.
                        ->options(fn () => User::query()->whereIn('role', ['admin', 'agent'])->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->default(fn () => auth()->id())
                        ->disabled(fn () => ! auth()->user()?->isAdmin())
                        ->dehydrated(),
                    Forms\Components\Toggle::make('is_demo')
                        ->label(__('panel.field.is_demo'))
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                        ->inline(false),
                    Forms\Components\Textarea::make('notes')->label(__('panel.client.notes'))->rows(3)->columnSpanFull(),
                ]),
        ];
    }

    protected static function duplicatesFor(Forms\Get $get, ?Client $record)
    {
        return Client::duplicatesOf([
            'email' => $get('email'),
            'phone' => $get('phone'),
            'us_dot_number' => $get('us_dot_number'),
            'mc_number' => $get('mc_number'),
        ], $record?->getKey());
    }

    protected static function duplicateWarning($matches): HtmlString
    {
        $items = $matches->map(function (array $match): string {
            /** @var Client $client */
            $client = $match['client'];
            $reasons = collect($match['reasons'])->map(fn ($r) => __('panel.client.duplicate_reason.'.$r))->implode(', ');
            $url = static::getUrl('edit', ['record' => $client]);

            return '<li><a href="'.e($url).'" target="_blank" class="font-semibold underline">'.e($client->company_name).'</a> ('.e($reasons).')</li>';
        })->implode('');

        return new HtmlString(
            '<div class="rounded-lg border border-amber-500/50 bg-amber-500/10 p-4 text-sm text-amber-300">'
            .'<p class="font-semibold">&#9888; '.e(__('panel.client.duplicate_title')).'</p>'
            .'<p class="mb-2">'.e(__('panel.client.duplicate_hint')).'</p><ul class="list-disc pl-5">'.$items.'</ul></div>'
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // The one searchable column runs the full client search (name, company, email, phone, DOT, MC).
                Tables\Columns\TextColumn::make('company_name')
                    ->label(__('panel.field.company_name'))
                    ->description(fn (Client $r) => $r->contact_name)
                    ->searchable(query: fn (Builder $query, string $search) => $query->search($search))
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_demo')
                    ->label('')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Client $r) => $r->is_demo ? __('app.demo_badge') : null),
                Tables\Columns\TextColumn::make('email')->label(__('panel.field.email')),
                Tables\Columns\TextColumn::make('phone')->label(__('panel.field.phone_number')),
                Tables\Columns\TextColumn::make('us_dot_number')->label(__('panel.field.us_dot_number')),
                Tables\Columns\TextColumn::make('mc_number')->label(__('panel.client.mc_number'))->toggleable(),
                Tables\Columns\TextColumn::make('commercial_stage')
                    ->label(__('panel.quote.commercial_status'))
                    ->badge()
                    ->state(fn (Client $r) => $r->commercialStage())
                    ->color(fn (?string $state) => $state ? QuoteResource::stageColor($state) : 'gray')
                    ->formatStateUsing(fn (?string $state) => $state ? __('panel.quote.stages.'.$state) : '—'),
                Tables\Columns\TextColumn::make('assignedUser.name')->label(__('panel.client.assigned_user')),
                Tables\Columns\TextColumn::make('applications_count')
                    ->label(__('panel.client.applications_count'))
                    ->counts('applications')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('panel.field.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('quotes'))
            ->searchPlaceholder(__('panel.client.search_placeholder'))
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label(__('panel.client.agent_filter'))
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_demo')->label(__('panel.field.is_demo')),
            ])
            ->actions([
                Tables\Actions\Action::make('newApplication')
                    ->label(__('panel.client.new_application'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->visible(fn (Client $record) => auth()->user()->can('update', $record) && auth()->user()->can('create', Application::class))
                    ->action(function (Client $record) {
                        $application = Application::createForClient($record, auth()->user());

                        return redirect(ApplicationResource::getUrl('edit', ['record' => $application]));
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ApplicationsRelationManager::class,
            ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
