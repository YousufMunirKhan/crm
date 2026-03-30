<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'category',
        'message',
        'media_url',
        'media_type',
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
