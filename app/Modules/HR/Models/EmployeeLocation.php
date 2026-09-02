<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One location reading, taken during one shift.
 *
 * @see \App\Modules\HR\Http\Controllers\EmployeeLocationController for the rule
 *      that a reading outside an open shift is refused rather than stored.
 */
class EmployeeLocation extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
        'battery_level',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Readings too vague to be worth drawing.
     *
     * A phone indoors on cell towers alone will happily report a position
     * accurate to two kilometres. Plotted next to a 5-metre GPS fix it looks
     * identical, and somebody will read a straight line between two of them as
     * a journey that never happened.
     */
    public const USABLE_ACCURACY_METRES = 500;

    public function isUsable(): bool
    {
        return $this->accuracy === null || (float) $this->accuracy <= self::USABLE_ACCURACY_METRES;
    }
}
