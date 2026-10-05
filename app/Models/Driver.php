<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'dob' => 'date',
        'date_of_hire' => 'date',
        'cdl_issue_date' => 'date',
        'cdl_expiry_date' => 'date',
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
        return (string) $this->driver_name;
    }
}
