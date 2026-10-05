<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes created / updated / deleted lines to the audit trail.
 *
 * A model using it may define:
 *  - auditContext(): ['application_id' => ..., 'client_id' => ...] to show up in those histories
 *  - auditLabel(): short human name of the record
 *  - $auditRedact: attributes whose value is never stored (only "changed")
 */
trait RecordsActivity
{
    /** Attributes that are noise in a history. */
    protected static array $auditIgnore = [
        'updated_at', 'created_at', 'token', 'verification_code', 'remember_token', 'sort_order',
    ];

    /** Personal identifiers: the log says they changed, never to what. */
    protected static array $auditAlwaysRedact = [
        'dob', 'cdl_number', 'password', 'signature_path', 'accepted_signature_path', 'accepted_snapshot', 'acceptance_token',
    ];

    public static function bootRecordsActivity(): void
    {
        static::created(fn (Model $model) => ActivityLog::record($model, 'created'));

        static::updated(function (Model $model): void {
            $changes = $model->auditChanges();

            if ($changes === []) {
                return;
            }

            $event = match (true) {
                array_key_exists('status', $changes) => 'status_changed',
                array_key_exists('stage', $changes) => 'stage_changed',
                default => 'updated',
            };
            ActivityLog::record($model, $event, $changes);
        });

        static::deleted(fn (Model $model) => ActivityLog::record($model, 'deleted'));
    }

    public function auditType(): string
    {
        return strtolower(class_basename($this));
    }

    public function auditLabel(): string
    {
        return (string) ($this->company_name ?? $this->name ?? '');
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public function auditChanges(): array
    {
        $redact = array_merge(static::$auditAlwaysRedact, $this->auditRedact ?? []);
        $changes = [];

        foreach (array_keys($this->getChanges()) as $field) {
            if (in_array($field, static::$auditIgnore, true)) {
                continue;
            }

            $old = $this->getOriginal($field);
            $new = $this->getAttribute($field);
            $hidden = in_array($field, $redact, true);

            $changes[$field] = [
                $hidden ? ActivityLog::HIDDEN : static::auditScalar($old),
                $hidden ? ActivityLog::HIDDEN : static::auditScalar($new),
            ];
        }

        return $changes;
    }

    /** Dates and objects as plain, readable text. */
    protected static function auditScalar(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i');
        }

        return is_array($value) ? json_encode($value) : $value;
    }
}
