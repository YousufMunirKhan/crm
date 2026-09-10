<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasAuditLog;

    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'date',
        'check_in_at',
        'check_in_photo_path',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_location_name',
        'check_in_location_accuracy',
        'check_in_location_captured_at',
        'check_in_location_source',
        'check_out_at',
        'check_out_photo_path',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_location_name',
        'check_out_location_accuracy',
        'check_out_location_captured_at',
        'check_out_location_source',
        'auto_closed_at',
        'work_hours',
    ];

    protected $appends = [
        'check_in_photo_url',
        'check_out_photo_url',
        'check_in_map_url',
        'check_out_map_url',
    ];

    /**
     * Kept in the table, kept out of responses.
     *
     * Whether a position was measured or came from somebody's set work address
     * is worth recording - if a shift is ever queried, that is the difference
     * between a reading and an assumption. It is not worth putting in front of
     * the person clocking in, or on a report where it reads as a mark against
     * them rather than a fact about their phone.
     *
     * Anyone who needs it can query the column.
     */
    protected $hidden = [
        'check_in_location_source',
        'check_out_location_source',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime',
        'check_in_location_captured_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_out_location_captured_at' => 'datetime',
        'auto_closed_at' => 'datetime',
        'work_hours' => 'decimal:2',
        'check_in_latitude' => 'decimal:7',
        'check_in_longitude' => 'decimal:7',
        'check_in_location_accuracy' => 'decimal:2',
        'check_out_latitude' => 'decimal:7',
        'check_out_longitude' => 'decimal:7',
        'check_out_location_accuracy' => 'decimal:2',
    ];

    /**
     * Today, as the business means it.
     *
     * Timestamps are stored in UTC and that is not changing - it is the only
     * sane way to keep them. But the day a shift is filed under is a UK day,
     * and for half the year UTC midnight lands at one in the morning here, so
     * anyone starting at half past midnight was being filed under yesterday.
     */
    public static function workingDate(): string
    {
        return now()->setTimezone(config('attendance.timezone', 'Europe/London'))->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function getCheckInPhotoUrlAttribute(): ?string
    {
        return $this->check_in_photo_path
            ? '/storage/' . ltrim($this->check_in_photo_path, '/')
            : null;
    }

    public function getCheckOutPhotoUrlAttribute(): ?string
    {
        return $this->check_out_photo_path
            ? '/storage/' . ltrim($this->check_out_photo_path, '/')
            : null;
    }

    public function getCheckInMapUrlAttribute(): ?string
    {
        if ($this->check_in_latitude === null || $this->check_in_longitude === null) {
            return null;
        }

        return 'https://www.google.com/maps?q=' . $this->check_in_latitude . ',' . $this->check_in_longitude;
    }

    public function getCheckOutMapUrlAttribute(): ?string
    {
        if ($this->check_out_latitude === null || $this->check_out_longitude === null) {
            return null;
        }

        return 'https://www.google.com/maps?q=' . $this->check_out_latitude . ',' . $this->check_out_longitude;
    }
}


