<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file attached to a quote (proposal, signed acceptance, binder, receipt). Stored privately. */
class QuoteDocument extends Model
{
    use RecordsActivity;

    public const TYPES = ['proposal_received', 'proposal_sent', 'signed_acceptance', 'binder', 'receipt', 'other'];

    public const DISK = 'local';

    protected $guarded = ['id'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array{application_id: ?int, client_id: ?int} */
    public function auditContext(): array
    {
        return ['application_id' => $this->quote?->application_id, 'client_id' => $this->quote?->client_id];
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
