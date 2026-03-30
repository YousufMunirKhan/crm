<?php

namespace App\Modules\Ticket\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (TicketAttachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
