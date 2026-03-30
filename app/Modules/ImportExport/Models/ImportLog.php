<?php

namespace App\Modules\ImportExport\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'type',
        'total_rows',
        'imported',
        'skipped',
        'errors',
        'status',
        'user_id',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

