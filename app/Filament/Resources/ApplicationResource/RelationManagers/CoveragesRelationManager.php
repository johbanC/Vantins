<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Models\Coverage;
use App\Models\CoverageType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CoveragesRelationManager extends RelationManager
{
    protected static string $relationship = 'coverages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.coverages_list');
    }

    protected function type(?int $id): ?CoverageType
    {
        return $id ? CoverageType::find($id) : null;
    }

    /** One amount field: a select when the catalog offers amounts, a free number otherwise. */
    protected function amountFields(string $field, string $column): array
    {
        $has = fn (Forms\Get $get) => (bool) $this->type((int) $get('coverage_type_id'))?->hasField($field);
        $options = fn (Forms\Get $get) => collect($this->type((int) $get('coverage_type_id'))?->options($field))
            ->mapWithKeys(fn ($amount) => [(string) $amount => '$'.number_format($amount)])->all();
        $offered = fn (Forms\Get $get) => $options($get) !== [];

        return [
            Forms\Components\Select::make($column)
                ->label(__('app.'.$field))
                ->options($options)
                ->native(false)
                ->required()
                ->visible(fn (Forms\Get $get) => $has($get) && $offered($get)),
            Forms\Components\TextInput::make($column)
                ->label(__('app.'.$field))
                ->numeric()->prefix('$')->gt(0)
                ->required()
                ->visible(fn (Forms\Get $get) => $has($get) && ! $offered($get)),
        ];
    }

    public function form(Form $form): Form
    {
        $owner = $this->getOwnerRecord();
        $has = fn (string $field) => fn (Forms\Get $get) => (bool) $this->type((int) $get('coverage_type_id'))?->hasField($field);

        return $form->columns(2)->schema([
            Forms\Components\Select::make('coverage_type_id')
                ->label(__('app.coverage'))
                ->options(fn () => CoverageType::query()->active()->orderBy('sort_order')->get()->mapWithKeys(fn ($t) => [$t->id => $t->name()]))
                ->required()
                ->native(false)
                ->live()
                // A coverage can only be added once ("Other" only with a different name).
                ->rules([fn (?Model $record) => function (string $attribute, $value, \Closure $fail) use ($owner, $record) {
                    $type = CoverageType::find($value);

                    if (! $type || $type->isRepeatable()) {
                        return;
                    }

                    $exists = Coverage::query()
                        ->where('application_id', $owner->getKey())
                        ->where('coverage_type_id', $value)
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($exists) {
                        $fail(__('app.validation.coverage_duplicate'));
                    }
                }]),

            Forms\Components\TextInput::make('custom_name')
                ->label(__('app.custom_name'))
                ->maxLength(190)
                ->required()
                ->visible($has('custom_name')),

            ...$this->amountFields('limit', 'limit_amount'),
            ...$this->amountFields('aggregate', 'aggregate_limit'),
            ...$this->amountFields('deductible', 'deductible'),

            Forms\Components\TextInput::make('underlying_coverage')
                ->label(__('app.underlying_coverage'))
                ->maxLength(190)
                ->required()
                ->visible($has('underlying')),

            Forms\Components\Select::make('details.vehicle_ids')
                ->label(__('app.coverage_units').': '.__('app.vehicles_schedule'))
                ->multiple()
                ->native(false)
                ->required()
                ->options(fn () => $this->units($owner->vehicles))
                ->visible($has('vehicles'))
                ->columnSpanFull(),

            Forms\Components\Select::make('details.trailer_ids')
                ->label(__('app.coverage_units').': '.__('app.trailers_schedule'))
                ->multiple()
                ->native(false)
                ->required()
                ->options(fn () => $this->units($owner->trailers))
                ->visible($has('trailers'))
                ->columnSpanFull(),

            Forms\Components\Textarea::make('notes')
                ->label(__('app.notes'))
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, string> */
    protected function units(Collection $units): array
    {
        return $units->mapWithKeys(fn ($u) => [$u->id => trim($u->year.' '.$u->make.' '.$u->vin) ?: '#'.$u->id])->all();
    }

    /**
     * Keep only what belongs to the chosen coverage and the readable name old readers expect.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        $type = CoverageType::findOrFail($data['coverage_type_id']);

        $data['coverage'] = $type->isRepeatable() ? ($data['custom_name'] ?? null) : $type->name_en;
        $data['custom_name'] = $type->isRepeatable() ? ($data['custom_name'] ?? null) : null;
        $data['needs_review'] = $type->isRepeatable();

        foreach (['limit' => 'limit_amount', 'aggregate' => 'aggregate_limit', 'deductible' => 'deductible', 'underlying' => 'underlying_coverage'] as $field => $column) {
            $data[$column] = $type->hasField($field) ? ($data[$column] ?? null) : null;
        }

        $details = array_filter([
            'vehicle_ids' => $type->hasField('vehicles') ? array_map('intval', $data['details']['vehicle_ids'] ?? []) : [],
            'trailer_ids' => $type->hasField('trailers') ? array_map('intval', $data['details']['trailer_ids'] ?? []) : [],
        ]);
        $data['details'] = $details ?: null;

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('coverage')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with('type'))
            ->columns([
                Tables\Columns\TextColumn::make('coverage')
                    ->label(__('app.coverage'))
                    ->state(fn (Coverage $r) => $r->displayName()),
                Tables\Columns\IconColumn::make('needs_review')
                    ->label(__('app.coverage_review_flag'))
                    ->boolean()
                    ->trueIcon('heroicon-o-flag')
                    ->falseIcon('')
                    ->trueColor('warning'),
                Tables\Columns\TextColumn::make('limit_amount')
                    ->label(__('app.limit'))
                    ->state(fn (Coverage $r) => $r->displayLimit()),
                Tables\Columns\TextColumn::make('deductible')
                    ->label(__('app.deductible'))
                    ->state(fn (Coverage $r) => Coverage::formatAmount($r->deductible)),
                Tables\Columns\TextColumn::make('details')
                    ->label(__('app.coverage_details'))
                    ->state(fn (Coverage $r) => $r->detailLines())
                    ->listWithLineBreaks()
                    ->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('app.add'))
                    ->mutateFormDataUsing(fn (array $data) => $this->normalize($data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $this->normalize($data)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
