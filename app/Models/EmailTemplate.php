<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'subject',
        'description',
        'content',
        'variables',
        'is_active',
        'is_prebuilt',
        'preview_image',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_prebuilt' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
