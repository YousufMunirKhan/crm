<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivity extends Model
{
    protected $table = 'daily_activities';

    protected $fillable = [
        'user_id',
        'activity_date',
        'description',
    ];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
