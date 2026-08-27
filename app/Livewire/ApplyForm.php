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

    public int $step = 1;

    public int $totalSteps = 7;

    /** Flat attribute bag for the single-row sections. */
    public array $form = [];

    /** Repeatable rows. */
    public array $drivers = [];
    public array $vehicles = [];
    public array $trailers = [];
    public array $coverages = [];

    public bool $disclosureAccepted = false;
    public string $signerName = '';
    public ?string $signatureData = null; // base64 PNG data URL from signature pad

    protected array $singleFields = [
        'company_name', 'company_representative', 'phone_number', 'email',
        'mailing_address', 'parking_address', 'effective_date', 'us_dot_number',
        'radius_of_operations', 'years_in_business', 'power_units', 'commodities_hauled',
        'total_policy_premium', 'agency_name', 'agency_phone', 'contact_agent_name',
    ];

    public function mount(string $token): void
    {
        $this->application = Application::where('token', $token)->firstOrFail();

        abort_if($this->application->status === 'issued', 410);

        App::setLocale($this->application->locale);

        foreach ($this->singleFields as $field) {
            $this->form[$field] = $this->application->{$field};
        }
        $this->form['effective_date'] = optional($this->application->effective_date)->format('Y-m-d');

        $this->drivers = $this->application->drivers->map(fn ($d) => $d->only([
            'driver_name', 'dob', 'cdl_number', 'state_issued', 'experience', 'date_of_hire',
        ]))->toArray();
        $this->vehicles = $this->application->vehicles->map(fn ($v) => $v->only([
            'year', 'make', 'vin', 'body_type', 'stated_value',
        ]))->toArray();
        $this->trailers = $this->application->trailers->map(fn ($t) => $t->only([
            'year', 'make', 'vin', 'body_type', 'stated_value',
        ]))->toArray();
        $this->coverages = $this->application->coverages->map(fn ($c) => $c->only([
            'coverage', 'limit_amount', 'deductible', 'premium',
        ]))->toArray();

        $this->signerName = $this->application->signer_name ?? '';
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, ['en', 'es'], true)) {
            return;
        }
        $this->application->update(['locale' => $locale]);
        App::setLocale($locale);
    }

    public function addRow(string $collection): void
    {
        $this->{$collection}[] = [];
    }

    public function removeRow(string $collection, int $index): void
    {
        unset($this->{$collection}[$index]);
        $this->{$collection} = array_values($this->{$collection});
    }

    public function persist(): void
    {
        $data = collect($this->form)
            ->only($this->singleFields)
            ->map(fn ($v) => $v === '' ? null : $v)
            ->toArray();

        $this->application->fill($data)->save();

        $this->syncRows('drivers', ['driver_name', 'dob', 'cdl_number', 'state_issued', 'experience', 'date_of_hire']);
        $this->syncRows('vehicles', ['year', 'make', 'vin', 'body_type', 'stated_value']);
        $this->syncRows('trailers', ['year', 'make', 'vin', 'body_type', 'stated_value']);
        $this->syncRows('coverages', ['coverage', 'limit_amount', 'deductible', 'premium']);
    }

    /** Column length caps so oversized input can never break the insert. */
    protected const MAX_LENGTHS = [
        'year' => 20, 'vin' => 64, 'make' => 190, 'body_type' => 190,
        'state_issued' => 40, 'experience' => 60, 'cdl_number' => 190,
        'driver_name' => 190, 'coverage' => 190, 'limit_amount' => 190, 'deductible' => 190,
    ];

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

    public function submit(): void
    {
        $this->validate([
            'signerName' => 'required|string|max:255',
            'disclosureAccepted' => 'accepted',
            'signatureData' => 'required|string',
        ]);

        $this->persist();

        $png = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $this->signatureData));
        $path = "signatures/{$this->application->token}.png";
        Storage::disk('public')->put($path, $png);

        $this->application->forceFill([
            'signer_name' => $this->signerName,
            'signature_path' => $path,
            'signed_ip' => request()->ip(),
            'disclosure_accepted_at' => now(),
        ])->save();

        $this->application->markStatus('submitted');

        $this->step = $this->totalSteps + 1; // thank-you screen
    }

    public function render()
    {
        return view('livewire.apply-form');
    }
}
