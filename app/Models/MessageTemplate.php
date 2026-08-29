<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        // Stable key the marketing agent maps to; NULL for hand-sent templates.
        'purpose',
        'message',
        'variables',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
