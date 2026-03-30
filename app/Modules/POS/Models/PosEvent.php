<?php

namespace App\Modules\POS\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;

class PosEvent extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'event_type',
        'payload',
        'external_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}


