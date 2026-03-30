<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailList extends Model
{
    protected $fillable = [
        'name',
        'original_file_name',
        'template_id',
        'created_by',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Models\EmailTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailListRecipient::class, 'email_list_id');
    }
}
