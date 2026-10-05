<?php

namespace App\Livewire;

use App\Models\Application;
use App\Models\CoverageType;
use App\Support\CoverageRules;
use App\Support\DataQuality;
use App\Support\PdfDocuments;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class ApplyForm extends Component
{
    public Application $application;

    /** A signed-in staff member may edit; the client only reviews + signs. */
    public bool $editable = false;

    /** Signed-in staff without permission to edit: they can look, never sign. */
    public bool $staffReadOnly = false;

    /** Already signed / issued: nobody edits, nobody re-signs. */
    public bool $locked = false;

    /** The client cancelled: the link just shows a notice. */
    public bool $cancelled = false;

    /** '' while working, then 'saved' (advisor) or 'signed'. */
    public string $done = '';

    public int $step = 1;

    public int $totalSteps = 7; // advisor: 1 applicant .. 6 finance, 7 review + sign

    public array $form = [];

    public array $drivers = [];

    public array $vehicles = [];

    public array $trailers = [];

    public array $coverages = [];

    public bool $disclosureAccepted = false;

    public string $signerName = '';

    public ?string $signatureData = null;

    protected array $singleFields = [
        'company_name', 'company_representative', 'phone_number', 'email',
        'mailing_address', 'parking_address', 'effective_date', 'us_dot_number',
        'radius_of_operations', 'years_in_business', 'power_units', 'commodities_hauled',
        'down_payment', 'number_of_payments', 'monthly_payment',
    ];

    protected const MAX_LENGTHS = [
        'year' => 20, 'vin' => 64, 'make' => 190, 'body_type' => 190,
        'state_issued' => 40, 'experience' => 60, 'cdl_number' => 190,
        'garaging_zip' => 10,
        'driver_name' => 190, 'coverage' => 190, 'limit_amount' => 190, 'deductible' => 190,
    ];

    public function mount(string $token): void
    {
        $this->application = Application::with(['drivers', 'vehicles', 'trailers', 'coverages.type'])
            ->where('token', $token)
            ->firstOrFail();

        App::setLocale($this->application->locale);

        $this->locked = $this->application->isLocked();
        $this->cancelled = $this->application->isCancelled();
        // Only staff allowed to change this application get the editable form; the client
        // (not signed in) reviews and signs. Other staff just look at it.
        $this->editable = auth()->check()
            && auth()->user()->can('update', $this->application)
            && ! $this->locked
            && ! $this->cancelled;
        $this->staffReadOnly = auth()->check() && ! $this->editable;

        foreach ($this->singleFields as $field) {
            $this->form[$field] = $this->application->{$field};
        }
        $this->form['effective_date'] = optional($this->application->effective_date)->format('Y-m-d');

        foreach (['drivers', 'vehicles', 'trailers'] as $rel) {
            $this->{$rel} = $this->application->{$rel}
                ->map(fn ($row) => $this->rowToArray($row, DataQuality::ROW_FIELDS[$rel]))
                ->all();
        }
        $this->coverages = $this->application->coverages->map(fn ($row) => [
            'id' => $row->id,
            'type' => $row->type?->key ?? CoverageType::REPEATABLE,
            'limit_amount' => $row->limit_amount,
            'aggregate_limit' => $row->aggregate_limit,
            'deductible' => $row->deductible,
            'underlying_coverage' => $row->underlying_coverage,
            'custom_name' => $row->custom_name ?? $row->coverage,
            'notes' => $row->notes,
            'vehicle_ids' => array_map('strval', (array) ($row->details['vehicle_ids'] ?? [])),
            'trailer_ids' => array_map('strval', (array) ($row->details['trailer_ids'] ?? [])),
        ])->all();

        $this->signerName = $this->application->signer_name ?? '';
    }

    /** Livewire skips mount() on every later request: re-apply the chosen language each time. */
    public function hydrate(): void
    {
        App::setLocale($this->application->locale);
    }

    /** Plain, form-friendly values: dates as Y-m-d, no objects in the Livewire state. */
    protected function rowToArray($row, array $fields): array
    {
        return ['id' => $row->id] + collect($fields)->mapWithKeys(function ($f) use ($row) {
            $value = $row->{$f};

            return [$f => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value];
        })->all();
    }

    public function switchLocale(string $locale): void
    {
        if (in_array($locale, ['en', 'es'], true)) {
            $this->application->update(['locale' => $locale]);
            App::setLocale($locale);
        }
    }

    public function addRow(string $collection): void
    {
        abort_unless($this->editable, 403);
        $this->{$collection}[] = [];
    }

    /** Tick / untick a catalog coverage. "Other" can be added again and again, the rest only once. */
    public function toggleCoverage(string $key): void
    {
        abort_unless($this->editable, 403);

        $type = CoverageType::catalog()->get($key);
        abort_unless($type, 422);

        $index = collect($this->coverages)->search(fn ($row) => ($row['type'] ?? null) === $key);

        if ($type->isRepeatable() || $index === false) {
            $this->coverages[] = ['type' => $key];

            return;
        }

        $this->removeRow('coverages', $index);
    }

    public function removeRow(string $collection, int $index): void
    {
        abort_unless($this->editable, 403);
        unset($this->{$collection}[$index]);
        $this->{$collection} = array_values($this->{$collection});
    }

    public function persist(): void
    {
        abort_unless($this->editable, 403);

        $data = collect($this->form)
            ->only($this->singleFields)
            ->map(fn ($v) => $v === '' ? null : $v)
            ->toArray();

        $this->application->fill($data)->save();

        $this->syncRows('drivers', DataQuality::ROW_FIELDS['drivers']);
        $this->syncRows('vehicles', DataQuality::ROW_FIELDS['vehicles']);
        $this->syncRows('trailers', DataQuality::ROW_FIELDS['trailers']);
        $this->syncRows('coverages', []);

        $this->application->refresh();
    }

    /** Columns of a coverage row from what was ticked and typed; null when the type is unknown. */
    protected function coveragePayload(array $row): ?array
    {
        $type = CoverageType::query()->where('key', $row['type'] ?? null)->first();

        if (! $type) {
            return null;
        }

        $text = fn (string $f, int $max = 190) => blank($row[$f] ?? null) ? null : mb_substr(trim((string) $row[$f]), 0, $max);
        $ids = fn (string $f) => array_values(array_map('intval', array_filter((array) ($row[$f] ?? []))));

        $details = array_filter([
            'vehicle_ids' => $type->hasField('vehicles') ? $ids('vehicle_ids') : [],
            'trailer_ids' => $type->hasField('trailers') ? $ids('trailer_ids') : [],
        ]);

        return [
            'coverage_type_id' => $type->id,
            'coverage' => $type->isRepeatable() ? $text('custom_name') : $type->name_en, // readable name kept for old readers
            'custom_name' => $type->isRepeatable() ? $text('custom_name') : null,
            'limit_amount' => $type->hasField('limit') ? $text('limit_amount', 40) : null,
            'aggregate_limit' => $type->hasField('aggregate') ? $text('aggregate_limit', 40) : null,
            'deductible' => $type->hasField('deductible') ? $text('deductible', 40) : null,
            'underlying_coverage' => $type->hasField('underlying') ? $text('underlying_coverage') : null,
            'notes' => $text('notes', 1000),
            'details' => $details ?: null,
            'needs_review' => $type->isRepeatable(),
        ];
    }

    /**
     * Save the rows of one list in place: rows that already exist (they carry their id) are
     * updated, new ones created, and the ones the advisor removed deleted. Nothing is
     * deleted and recreated, so the audit trail only records what really changed.
     */
    protected function syncRows(string $relation, array $fields): void
    {
        $existing = $this->application->{$relation}()->get()->keyBy('id');
        $this->{$relation} = array_values($this->{$relation});
        $keep = [];

        foreach ($this->{$relation} as $i => $row) {
            if ($relation === 'coverages') {
                $payload = $this->coveragePayload($row);

                if ($payload === null) {
                    unset($this->coverages[$i]['id']);

                    continue;
                }

                $payload['sort_order'] = $i;
                $model = $existing->get($row['id'] ?? null);
                if ($model) {
                    $model->update($payload);
                } else {
                    $model = $this->application->coverages()->create($payload);
                }

                $keep[] = $model->id;
                $this->coverages[$i]['id'] = $model->id;

                continue;
            }

            $payload = collect($fields)
                ->mapWithKeys(function ($f) use ($row) {
                    $value = $row[$f] ?? null;
                    if ($f === 'has_physical_damage') {
                        return [$f => filter_var($value, FILTER_VALIDATE_BOOLEAN)];
                    }
                    if ($value === '' || $value === null) {
                        return [$f => null];
                    }
                    if (is_string($value) && isset(self::MAX_LENGTHS[$f])) {
                        $value = mb_substr($value, 0, self::MAX_LENGTHS[$f]);
                    }

                    return [$f => $value];
                })
                ->toArray();

            // A blank row is dropped (and deleted if it used to exist).
            if (collect($payload)->filter()->isEmpty()) {
                unset($this->{$relation}[$i]['id']);

                continue;
            }

            $payload['sort_order'] = $i;
            $model = $existing->get($row['id'] ?? null);

            if ($model) {
                $model->update($payload);
            } else {
                $model = $this->application->{$relation}()->create($payload);
            }

            $keep[] = $model->id;
            $this->{$relation}[$i]['id'] = $model->id;
        }

        $existing->except($keep)->each->delete();
    }

    /** Hard validation of what the advisor typed in one step. Soft warnings never block. */
    protected function validateStep(int $step): bool
    {
        $errors = match ($step) {
            1, 6 => DataQuality::validateApplicant($this->form),
            2 => DataQuality::validateRows('drivers', $this->drivers),
            3 => DataQuality::validateRows('vehicles', $this->vehicles),
            4 => DataQuality::validateRows('trailers', $this->trailers),
            5 => CoverageRules::validateRows(
                $this->coverages,
                CoverageType::query()->get()->keyBy('key'),
                collect($this->vehicles)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all(),
                collect($this->trailers)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all(),
            ),
            default => [],
        };

        return $this->report($errors);
    }

    /** Validate everything; on failure jump to the first step that has an error. */
    protected function validateAll(): bool
    {
        foreach ([1, 2, 3, 4, 5, 6] as $step) {
            if (! $this->validateStep($step)) {
                $this->step = $step;

                return false;
            }
        }

        return true;
    }

    protected function report(array $errors): bool
    {
        $this->resetErrorBag();

        foreach ($errors as $key => $message) {
            $this->addError($key, $message);
        }
        if ($errors) {
            $this->addError('summary', __('app.fix_errors'));
        }

        return $errors === [];
    }

    public function next(): void
    {
        abort_unless($this->editable, 403);

        if (! $this->validateStep($this->step)) {
            return;
        }

        $this->persist();
        $this->step = min($this->step + 1, $this->totalSteps);
    }

    public function back(): void
    {
        abort_unless($this->editable, 403);

        // Going back must never trap the advisor: save only what is valid.
        if ($this->validateStep($this->step)) {
            $this->persist();
        }
        $this->step = max($this->step - 1, 1);
    }

    /** Advisor: store what has been entered without a signature yet. */
    public function saveDraft(): void
    {
        abort_unless($this->editable, 403);

        if (! $this->validateAll()) {
            return;
        }

        $this->persist();
        $this->done = 'saved';
    }

    /** Client (or advisor doing assisted fill): accept disclosure and sign. */
    public function sign(): void
    {
        abort_if($this->locked || $this->cancelled, 410);
        abort_if($this->staffReadOnly, 403);

        $this->validate([
            'signerName' => 'required|string|max:255',
            'disclosureAccepted' => 'accepted',
            'signatureData' => 'required|string',
        ]);

        if ($this->editable) {
            if (! $this->validateAll()) {
                return;
            }
            $this->persist();
        }

        $png = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $this->signatureData));
        $path = "signatures/{$this->application->token}.png";
        Storage::disk('public')->put($path, $png);

        $this->application->forceFill([
            'signer_name' => $this->signerName,
            'signature_path' => $path,
            'signed_ip' => request()->ip(),
            'disclosure_accepted_at' => now(),
        ])->save();

        $this->application->markStatus('signed');

        // Keep the PDF exactly as it was signed. A rendering problem must not lose the signature.
        try {
            PdfDocuments::storeSignedApplication($this->application->refresh());
        } catch (\Throwable $e) {
            report($e);
        }

        $this->done = 'signed';
        $this->locked = true;
    }

    public function render()
    {
        return view('livewire.apply-form', [
            'catalog' => CoverageType::catalog(),
            'types' => CoverageType::query()->get()->keyBy('key'),
        ]);
    }
}
