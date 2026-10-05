<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

/**
 * One immutable line of the audit trail. Written by the RecordsActivity trait.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The audit trail can never be edited or erased.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>|null  $changes
     */
    public static function record(Model $subject, string $event, ?array $changes = null): self
    {
        $context = method_exists($subject, 'auditContext') ? $subject->auditContext() : [];

        return static::create([
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'event' => $event,
            'subject_type' => $subject->auditType(),
            'subject_id' => $subject->getKey(),
            'subject_label' => mb_substr((string) $subject->auditLabel(), 0, 250) ?: null,
            'application_id' => $context['application_id'] ?? null,
            'client_id' => $context['client_id'] ?? null,
            'changes' => $changes ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who did it, in words: the advisor's name, or the client when they act through their link. */
    public function actorName(): string
    {
        return $this->user_name ?: __('panel.audit.guest');
    }

    public function eventLabel(): string
    {
        return __('panel.audit.events.'.$this->event);
    }

    public function subjectLabel(): string
    {
        $type = __('panel.audit.subjects.'.$this->subject_type);

        return $this->subject_label ? "{$type}: {$this->subject_label}" : $type;
    }

    /** @return list<string> "Field: old → new", one per change, in the panel language. */
    public function summaryLines(): array
    {
        return collect($this->getAttribute('changes') ?? [])
            ->map(fn (array $pair, string $field) => static::fieldLabel($field).': '
                .static::formatValue($field, $pair[0] ?? null).' → '.static::formatValue($field, $pair[1] ?? null))
            ->values()
            ->all();
    }

    protected static function fieldLabel(string $field): string
    {
        if ($field === 'status') {
            return __('panel.status.label');
        }

        foreach (["app.{$field}", "panel.field.{$field}", "panel.client.{$field}", "panel.user.{$field}"] as $key) {
            if (Lang::has($key)) {
                return __($key);
            }
        }

        return $field;
    }

    protected static function formatValue(string $field, mixed $value): string
    {
        if ($value === self::HIDDEN) {
            return __('panel.audit.hidden');
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? __('app.yes') : __('app.no');
        }
        if ($field === 'status') {
            return __('panel.status.'.$value);
        }
        if ($field === 'role') {
            return __('panel.user.roles.'.$value);
        }

        return mb_strimwidth((string) $value, 0, 60, '…');
    }

    /** Stored instead of the real value for personal identifiers. */
    public const HIDDEN = '__hidden__';
}
