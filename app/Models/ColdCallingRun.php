<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColdCallingRun extends Model
{
    protected $fillable = [
        'user_id',
        'postcode_input',
        'postcode_normalized',
        'radius_meters',
        'status',
        'new_count',
        'duplicate_count',
        'error_count',
        'details_fetched',
        'error_message',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
