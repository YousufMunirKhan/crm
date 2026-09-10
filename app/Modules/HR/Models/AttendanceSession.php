<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of a day somebody was clocked in for.
 *
 * A day can have several. The day row above this one keeps the summary that
 * every report already reads; this holds what actually happened.
 */
class AttendanceSession extends Model
{
    protected $table = 'attendance_sessions';

    protected $fillable = [
        'attendance_id',
        'user_id',
        'sequence',
        'check_in_at',
        'check_in_photo_path',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_location_name',
        'check_in_location_accuracy',
        'check_in_location_source',
        'check_out_at',
        'check_out_photo_path',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_location_name',
        'check_out_location_accuracy',
        'check_out_location_source',
        'auto_closed_at',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'auto_closed_at' => 'datetime',
        'check_in_latitude' => 'decimal:7',
        'check_in_longitude' => 'decimal:7',
        'check_in_location_accuracy' => 'decimal:2',
        'check_out_latitude' => 'decimal:7',
        'check_out_longitude' => 'decimal:7',
        'check_out_location_accuracy' => 'decimal:2',
    ];

    /**
     * Same reasoning as on the day row: which readings were measured and which
     * came from a set work address is worth keeping and not worth displaying.
     */
    protected $hidden = [
        'check_in_location_source',
        'check_out_location_source',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Still clocked in - not closed by the person, nor given up on. */
    public function scopeOpen($query)
    {
        return $query->whereNull('check_out_at')->whereNull('auto_closed_at');
    }
}
