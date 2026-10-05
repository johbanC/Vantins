<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coverage extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'premium' => 'decimal:2',
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
        return (string) $this->coverage;
    }
}
