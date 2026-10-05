<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\CarrierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An insurance company. Internal data: it is never shown on the client's pages. */
class Carrier extends Model
{
    /** @use HasFactory<CarrierFactory> */
    use HasFactory, RecordsActivity;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function auditLabel(): string
    {
        return $this->name;
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
