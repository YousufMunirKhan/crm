<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExpenseAttachment extends Model
{
    protected $fillable = [
        'expense_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (ExpenseAttachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
