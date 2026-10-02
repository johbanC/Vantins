<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
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
}
