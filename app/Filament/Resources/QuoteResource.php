<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\QuoteResource\Pages;
use App\Filament\Resources\QuoteResource\RelationManagers;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\CoverageType;
use App\Models\Quote;
use App\Models\User;
use App\Support\Format;
use App\Support\QuotePipeline;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Quotes: one record per carrier / alternative, each with its own place in the pipeline.
 */
class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('panel.quote.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.quote.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['carrier', 'application', 'producer']);
    }

    public static function stageColor(string $stage): string
    {
        return match ($stage) {
            'lead' => 'gray',
            'quote_sent' => 'info',
            'accepted' => 'primary',
            'binder' => 'warning',
            'paid' => 'success',
            'sold' => 'success',
            default => 'danger',
        };
    }

    // ---------------------------------------------------------------- form ----------------

    /** Applications the user may quote for: agents only their own, admins all. */
    protected static function applicationOptions(?string $search = null): array
    {
        $user = auth()->user();

        return Application::query()
            ->when(! $user->isAdmin(), fn (Builder $q) => $q->where('created_by', $user->id))
            ->when($search, fn (Builder $q) => $q->where('company_name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest()->limit(30)->pluck('company_name', 'id')->all();
    }

    /** The application's own coverages, as a starting point for this carrier's lines. */
    public static function coveragesFrom(?Application $application): array
    {
        if (! $application) {
            return [];
        }

        return $application->coverages()->with('type')->get()->map(fn ($c) => [
            'coverage_type_id' => $c->coverage_type_id,
            'custom_name' => $c->custom_name,
            'limit_amount' => is_numeric($c->limit_amount) ? $c->limit_amount : null,
            'aggregate_limit' => is_numeric($c->aggregate_limit) ? $c->aggregate_limit : null,
            'deductible' => is_numeric($c->deductible) ? $c->deductible : null,
            'details' => $c->details,
        ])->all();
    }

    public static function form(Form $form): Form
    {
        $money = fn (string $name, string $label) => Forms\Components\TextInput::make($name)
            ->label($label)->numeric()->prefix('$')->minValue(0)->maxValue(99999999)->live(onBlur: true);

        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\Select::make('application_id')
                    ->label(__('panel.quote.application'))
                    ->options(fn () => static::applicationOptions())
                    ->getSearchResultsUsing(fn (string $search) => static::applicationOptions($search))
                    ->getOptionLabelUsing(fn ($value) => Application::find($value)?->company_name)
                    ->default(fn () => request()->integer('application') ?: null)
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->disabledOn('edit')
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, string $operation) {
                        if ($operation === 'create' && $state && collect($get('coverages'))->filter()->isEmpty()) {
                            $set('coverages', static::coveragesFrom(Application::find($state)));
                        }
                    }),
                Forms\Components\Select::make('carrier_id')
                    ->label(__('panel.quote.carrier'))
                    ->helperText(__('panel.quote.carrier_internal'))
                    ->options(fn () => Carrier::active()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->createOptionForm(CarrierResource::formSchema())
                    ->createOptionUsing(fn (array $data) => Carrier::create($data)->getKey())
                    ->createOptionAction(fn ($action) => $action->visible(fn () => auth()->user()->can('create', Carrier::class))),
                Forms\Components\TextInput::make('product')->label(__('panel.quote.product'))->maxLength(255),
                Forms\Components\Select::make('producer_id')
                    ->label(__('panel.quote.producer'))
                    ->options(fn () => User::query()->whereIn('role', ['admin', 'agent'])->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->native(false),
                Forms\Components\Placeholder::make('version_info')
                    ->label(__('panel.quote.version'))
                    ->content(fn (?Quote $record) => $record ? 'v'.$record->version.($record->isCurrent() ? '' : ' — '.__('panel.quote.replaced')) : 'v1'),
            ]),

            Forms\Components\Section::make(__('panel.quote.section_lead'))->columns(2)->schema([
                Forms\Components\Textarea::make('qualification_note')->label(__('panel.quote.qualification_note'))->required()->rows(2)->maxLength(2000)->columnSpanFull(),
                Forms\Components\TextInput::make('next_step')->label(__('panel.quote.next_step'))->required()->maxLength(255),
                Forms\Components\DatePicker::make('follow_up_at')->label(__('panel.quote.follow_up_at'))->native(false),
            ]),

            Forms\Components\Section::make(__('panel.quote.section_offer'))->columns(3)->schema([
                Forms\Components\DatePicker::make('quoted_at')->label(__('panel.quote.quoted_at'))->native(false),
                Forms\Components\DatePicker::make('expires_at')->label(__('panel.quote.expires_at'))->native(false),
                Forms\Components\DatePicker::make('effective_date')->label(__('panel.quote.effective_date'))->native(false),
            ]),

            Forms\Components\Section::make(__('panel.quote.section_coverages'))
                ->description(__('panel.quote.section_coverages_hint'))
                ->schema([
                    Forms\Components\Repeater::make('coverages')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->defaultItems(0)
                        ->default(fn () => static::coveragesFrom(Application::find(request()->integer('application') ?: null)))
                        ->addActionLabel(__('panel.quote.add_coverage'))
                        ->columns(6)
                        ->rules([fn () => function (string $attribute, $rows, \Closure $fail) {
                            $typeIds = collect($rows)->pluck('coverage_type_id')->filter()->all();
                            $repeatable = CoverageType::where('key', CoverageType::REPEATABLE)->value('id');
                            $duplicated = collect($typeIds)->reject(fn ($id) => $id == $repeatable)->duplicates();

                            if ($duplicated->isNotEmpty()) {
                                $fail(__('app.validation.coverage_duplicate'));
                            }
                        }])
                        ->schema([
                            Forms\Components\Select::make('coverage_type_id')
                                ->label(__('panel.quote.coverage'))
                                ->options(fn () => CoverageType::query()->active()->orderBy('sort_order')->get()->mapWithKeys(fn ($t) => [$t->id => $t->name()]))
                                ->required()->native(false)->live()->columnSpan(2),
                            Forms\Components\TextInput::make('custom_name')
                                ->label(__('app.custom_name'))->maxLength(190)->required()
                                ->visible(fn (Forms\Get $get) => CoverageType::find($get('coverage_type_id'))?->isRepeatable() ?? false),
                            Forms\Components\TextInput::make('limit_amount')->label(__('panel.quote.limit'))->numeric()->prefix('$')->minValue(0),
                            Forms\Components\TextInput::make('aggregate_limit')->label(__('panel.quote.aggregate'))->numeric()->prefix('$')->minValue(0)
                                ->visible(fn (Forms\Get $get) => CoverageType::find($get('coverage_type_id'))?->hasField('aggregate') ?? false),
                            Forms\Components\TextInput::make('deductible')->label(__('panel.quote.deductible'))->numeric()->prefix('$')->minValue(0),
                            Forms\Components\TextInput::make('premium')->label(__('panel.quote.premium'))->numeric()->prefix('$')->minValue(0),
                        ]),
                ]),

            Forms\Components\Section::make(__('panel.quote.section_plan'))->columns(3)->schema([
                $money('carrier_premium', __('panel.quote.carrier_premium')),
                $money('fees', __('panel.quote.fees')),
                $money('producer_fee', __('panel.quote.producer_fee')),
                $money('down_payment', __('panel.quote.down_payment')),
                Forms\Components\TextInput::make('number_of_payments')->label(__('panel.quote.number_of_payments'))->numeric()->integer()->minValue(0)->maxValue(60)->live(onBlur: true),
                $money('installment_amount', __('panel.quote.installment_amount')),
            ]),

            Forms\Components\Section::make(__('panel.quote.section_totals'))->columns(4)->schema([
                static::total('total_cost', fn (Quote $q) => $q->totalCost()),
                static::total('amount_financed', fn (Quote $q) => $q->amountFinanced()),
                static::total('total_payable', fn (Quote $q) => $q->totalPayable()),
                static::total('finance_charge', fn (Quote $q) => $q->financeCharge())->helperText(__('panel.quote.finance_charge_hint')),
            ]),
        ]);
    }

    /** A read-only total, recomputed live from what is typed in the plan fields. */
    protected static function total(string $name, \Closure $compute): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($name)
            ->label(__('panel.quote.'.$name))
            ->content(function (Forms\Get $get) use ($compute): string {
                $quote = new Quote([
                    'carrier_premium' => (float) $get('carrier_premium'),
                    'fees' => (float) $get('fees'),
                    'producer_fee' => (float) $get('producer_fee'),
                    'down_payment' => (float) $get('down_payment'),
                    'number_of_payments' => (int) $get('number_of_payments'),
                    'installment_amount' => (float) $get('installment_amount'),
                ]);
                $value = $compute($quote);

                return ($value < 0 ? '-' : '').'$'.number_format(abs($value), 2);
            });
    }

    // --------------------------------------------------------------- actions --------------

    /** Move the quote along the pipeline, asking for the evidence that stage needs. */
    public static function moveAction(string $actionClass = Tables\Actions\Action::class)
    {
        // field => [stages that ask for it]
        $stagesByField = [];
        foreach (Quote::allStages() as $stage) {
            foreach (QuotePipeline::evidenceFields($stage) as $field => $spec) {
                $stagesByField[$field]['type'] = $spec['type'];
                $stagesByField[$field]['stages'][] = $stage;
                $stagesByField[$field]['required'][$stage] = $spec['required'];
            }
        }

        $fields = [];
        foreach ($stagesByField as $field => $info) {
            $component = match ($info['type']) {
                'date' => Forms\Components\DatePicker::make($field)->native(false),
                'textarea' => Forms\Components\Textarea::make($field)->rows(2)->maxLength(2000),
                'reason' => Forms\Components\Select::make($field)->native(false)
                    ->options(collect(Quote::LOSS_REASONS)->mapWithKeys(fn ($r) => [$r => __('panel.quote.loss_reasons.'.$r)])->all()),
                default => Forms\Components\TextInput::make($field)->maxLength(255),
            };

            $fields[] = $component
                ->label(__('panel.quote.'.$field))
                ->visible(fn (Forms\Get $get) => in_array($get('to'), $info['stages'], true))
                ->required(fn (Forms\Get $get) => (bool) ($info['required'][$get('to')] ?? false));
        }

        return $actionClass::make('move')
            ->label(__('panel.quote.move'))
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->modalHeading(__('panel.quote.move_heading'))
            ->visible(fn (Quote $record) => QuotePipeline::targets($record, auth()->user()) !== [])
            ->form(fn (Quote $record) => [
                Forms\Components\Select::make('to')
                    ->label(__('panel.quote.move_to'))
                    ->options(collect(QuotePipeline::targets($record, auth()->user()))->mapWithKeys(fn ($s) => [$s => __('panel.quote.stages.'.$s)])->all())
                    ->required()->native(false)->live(),
                Forms\Components\Placeholder::make('missing')
                    ->hiddenLabel()
                    ->visible(fn (Forms\Get $get) => $get('to') !== null && static::missingFor($record, $get('to')) !== [])
                    ->content(fn (Forms\Get $get) => new HtmlString(
                        '<div class="rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-300"><p class="font-semibold">'
                        .e(__('panel.quote.evidence_hint')).'</p><ul class="list-disc pl-5">'
                        .collect(static::missingFor($record, $get('to')))->map(fn ($m) => '<li>'.e($m).'</li>')->implode('').'</ul></div>'
                    )),
                ...$fields,
                Forms\Components\Textarea::make('note')->label(__('panel.quote.move_note'))->rows(2)->maxLength(2000),
            ])
            ->action(function (Quote $record, array $data, $action) {
                $to = $data['to'];

                try {
                    QuotePipeline::move($record, $to, collect($data)->except(['to', 'note', 'missing'])->filter(fn ($v) => filled($v))->all(), auth()->user(), $data['note'] ?? null);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(__('panel.quote.errors.not_allowed'))
                        ->body(collect($e->errors())->flatten()->implode("\n"))->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('panel.quote.move_done', ['stage' => __('panel.quote.stages.'.$to)]))->send();
            });
    }

    /** What still stands between the quote and a stage, said in words. */
    protected static function missingFor(Quote $quote, string $to): array
    {
        $missing = $to === 'quote_sent' ? array_values(QuotePipeline::readyToSendErrors($quote)) : [];

        if ($type = QuotePipeline::requiredDocument($to)) {
            if (! $quote->documents()->where('type', $type)->exists()) {
                $missing[] = __('panel.quote.errors.document_required', ['type' => __('panel.quote.document_types.'.$type)]);
            }
        }

        return $missing;
    }

    public static function reviseAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('revise')
            ->label(__('panel.quote.revise'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('panel.quote.revise_confirm'))
            ->visible(fn (Quote $record) => auth()->user()->can('manage', $record) && $record->isOpen())
            ->action(function (Quote $record) {
                $new = $record->revise();

                Notification::make()->success()->title(__('panel.quote.revise_done', ['version' => $new->version]))->send();

                return redirect(static::getUrl('edit', ['record' => $new]));
            });
    }

    /** Show / revoke / renew the client's signing link. */
    public static function linkActions(string $actionClass = Tables\Actions\Action::class): array
    {
        $visible = fn (Quote $record) => auth()->user()->can('manage', $record) && $record->stage === 'quote_sent' && $record->acceptanceStatus() !== 'none';

        return [
            $actionClass::make('link')
                ->label(__('panel.quote.link'))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible($visible)
                ->modalHeading(__('panel.quote.link_copy'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament-actions::modal.actions.cancel.label'))
                ->modalContent(fn (Quote $record) => view('filament.quote-link', ['quote' => $record])),
            $actionClass::make('revokeLink')
                ->label(__('panel.quote.link_revoke'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Quote $record) => $visible($record) && $record->acceptanceStatus() === 'open')
                ->action(function (Quote $record) {
                    $record->revokeAcceptanceLink();
                    Notification::make()->success()->title(__('panel.quote.link_revoked'))->send();
                }),
            $actionClass::make('newLink')
                ->label(__('panel.quote.link_new'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (Quote $record) => $visible($record) && $record->acceptanceStatus() !== 'accepted' && (! $record->expires_at || $record->expires_at->gte(today())))
                ->action(function (Quote $record) {
                    $record->issueAcceptanceLink();
                    $record->application->forceFill(['selected_quote_id' => $record->id])->save();
                    Notification::make()->success()->title(__('panel.quote.link_new_done'))->send();
                }),
        ];
    }

    // ----------------------------------------------------------------- table -------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('application.company_name')
                    ->label(__('panel.quote.application'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Quote $r) => 'v'.$r->version.($r->isCurrent() ? '' : ' · '.__('panel.quote.replaced'))),
                Tables\Columns\TextColumn::make('is_demo')
                    ->label('')->badge()->color('warning')
                    ->state(fn (Quote $r) => $r->is_demo ? __('app.demo_badge') : null),
                Tables\Columns\TextColumn::make('carrier.name')->label(__('panel.quote.carrier'))->searchable(),
                Tables\Columns\TextColumn::make('stage')
                    ->label(__('panel.quote.stage'))
                    ->badge()
                    ->color(fn (string $state) => static::stageColor($state))
                    ->formatStateUsing(fn (string $state) => __('panel.quote.stages.'.$state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label(__('panel.quote.total_cost'))
                    ->state(fn (Quote $r) => Format::money($r->totalCost())),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('panel.quote.expires_at'))
                    ->formatStateUsing(fn ($state) => Format::date($state))
                    ->color(fn (Quote $r) => $r->isOpen() && $r->expires_at?->lt(today()) ? 'danger' : ($r->isOpen() && $r->expires_at?->lte(today()->addDays(3)) ? 'warning' : null))
                    ->sortable(),
                Tables\Columns\TextColumn::make('follow_up_at')
                    ->label(__('panel.quote.follow_up_at'))
                    ->formatStateUsing(fn ($state) => Format::date($state))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('producer.name')->label(__('panel.quote.producer'))->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('stage')
                    ->label(__('panel.quote.filter_stage'))
                    ->multiple()
                    ->options(collect(Quote::allStages())->mapWithKeys(fn ($s) => [$s => __('panel.quote.stages.'.$s)])->all()),
                Tables\Filters\SelectFilter::make('carrier_id')
                    ->label(__('panel.quote.filter_carrier'))
                    ->relationship('carrier', 'name')
                    ->searchable()->preload(),
                Tables\Filters\SelectFilter::make('producer_id')
                    ->label(__('panel.quote.filter_agent'))
                    ->relationship('producer', 'name')
                    ->searchable()->preload(),
                Tables\Filters\TernaryFilter::make('current')
                    ->label(__('panel.quote.current_only'))
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('superseded_at'),
                        false: fn (Builder $q) => $q->whereNotNull('superseded_at'),
                        blank: fn (Builder $q) => $q,
                    ),
                Tables\Filters\TernaryFilter::make('is_demo')->label(__('panel.field.is_demo')),
            ])
            ->actions([
                static::moveAction(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\StageChangesRelationManager::class,
            ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'view' => Pages\ViewQuote::route('/{record}'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
