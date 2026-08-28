<?php

namespace App\Livewire;

use App\Models\Application;
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

    /** Already signed / issued: nobody edits, nobody re-signs. */
    public bool $locked = false;

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
        'driver_name' => 190, 'coverage' => 190, 'limit_amount' => 190, 'deductible' => 190,
    ];

    public function mount(string $token): void
    {
        $this->application = Application::with(['drivers', 'vehicles', 'trailers', 'coverages'])
            ->where('token', $token)
            ->firstOrFail();

        App::setLocale($this->application->locale);

        $this->locked = $this->application->isLocked();
        $this->editable = auth()->check() && ! $this->locked;

        foreach ($this->singleFields as $field) {
            $this->form[$field] = $this->application->{$field};
        }
        $this->form['effective_date'] = optional($this->application->effective_date)->format('Y-m-d');

        $this->drivers = $this->application->drivers->map->only(['driver_name', 'dob', 'cdl_number', 'state_issued', 'experience', 'date_of_hire'])->toArray();
        $this->vehicles = $this->application->vehicles->map->only(['year', 'make', 'vin', 'body_type', 'stated_value'])->toArray();
        $this->trailers = $this->application->trailers->map->only(['year', 'make', 'vin', 'body_type', 'stated_value'])->toArray();
        $this->coverages = $this->application->coverages->map->only(['coverage', 'limit_amount', 'deductible'])->toArray();

        $this->signerName = $this->application->signer_name ?? '';
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

        $this->syncRows('drivers', ['driver_name', 'dob', 'cdl_number', 'state_issued', 'experience', 'date_of_hire']);
        $this->syncRows('vehicles', ['year', 'make', 'vin', 'body_type', 'stated_value']);
        $this->syncRows('trailers', ['year', 'make', 'vin', 'body_type', 'stated_value']);
        $this->syncRows('coverages', ['coverage', 'limit_amount', 'deductible']);

        $this->application->refresh();
    }

    protected function syncRows(string $relation, array $fields): void
    {
        $this->application->{$relation}()->delete();
        foreach (array_values($this->{$relation}) as $i => $row) {
            $payload = collect($fields)
                ->mapWithKeys(function ($f) use ($row) {
                    $value = $row[$f] ?? null;
                    if ($value === '' || $value === null) {
                        return [$f => null];
                    }
                    if (is_string($value) && isset(self::MAX_LENGTHS[$f])) {
                        $value = mb_substr($value, 0, self::MAX_LENGTHS[$f]);
                    }

                    return [$f => $value];
                })
                ->toArray();
            if (collect($payload)->filter()->isEmpty()) {
                continue;
            }
            $payload['sort_order'] = $i;
            $this->application->{$relation}()->create($payload);
        }
    }

    public function next(): void
    {
        $this->persist();
        $this->step = min($this->step + 1, $this->totalSteps);
    }

    public function back(): void
    {
        $this->persist();
        $this->step = max($this->step - 1, 1);
    }

    /** Advisor: store what has been entered without a signature yet. */
    public function saveDraft(): void
    {
        $this->persist();
        $this->done = 'saved';
    }

    /** Client (or advisor doing assisted fill): accept disclosure and sign. */
    public function sign(): void
    {
        abort_if($this->locked, 410);

        $this->validate([
            'signerName' => 'required|string|max:255',
            'disclosureAccepted' => 'accepted',
            'signatureData' => 'required|string',
        ]);

        if ($this->editable) {
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

        $this->done = 'signed';
        $this->locked = true;
    }

    public function render()
    {
        return view('livewire.apply-form');
    }
}
