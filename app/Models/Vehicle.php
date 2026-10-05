<?php

namespace App\Models;

use App\Models\Concerns\LocksWithSignedApplication;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use LocksWithSignedApplication;
    use RecordsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'stated_value' => 'decimal:2',
        'has_physical_damage' => 'boolean',
        'physical_damage_value' => 'decimal:2',
        'physical_damage_deductible' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return array{application_id: ?int, client_id: ?int} */
    public function auditContext(): array
    {
        return ['application_id' => $this->application_id, 'client_id' => $this->application?->client_id];
    }

    public function auditLabel(): string
    {
        return trim($this->year.' '.$this->make.' '.$this->vin);
    }
}
