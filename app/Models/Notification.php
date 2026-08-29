<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * In-app notification.
 *
 * The model was an empty stub and the table had only an id, so nothing could
 * be written and every read failed on a missing column.
 */
class Notification extends Model
{
    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    protected $attributes = [
        'notifiable_type' => User::class,
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Convenience for the common case: notify one staff user.
     */
    public static function notifyUser(int $userId, string $type, string $title, string $message, array $data = []): self
    {
        return static::create([
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'title' => $title,
            'message' => $message,
            'data' => $data ?: null,
        ]);
    }
}
