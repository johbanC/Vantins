<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coverage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'premium' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $sync = fn (Coverage $coverage) => Application::find($coverage->application_id)?->recalculatePremium();

        static::saved($sync);
        static::deleted($sync);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
