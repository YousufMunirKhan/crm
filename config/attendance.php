<?php

return [

    /*
    |--------------------------------------------------------------------------
    | When an open shift is treated as abandoned
    |--------------------------------------------------------------------------
    |
    | Hours since clocking in with no clock-out. Sixteen leaves room for a long
    | day - a twelve hour shift is a shift, not a mistake - while still catching
    | the ones nobody ever closed, which is a quarter of every attendance row on
    | this system.
    |
    | Closing one does not invent a finish time. The shift stops counting as
    | open; the hours stay unknown, because nobody recorded them.
    |
    */

    'abandon_after_hours' => (int) env('ATTENDANCE_ABANDON_AFTER_HOURS', 16),

    /*
    |--------------------------------------------------------------------------
    | The working day
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC, which is the only sane way to keep them. But
    | "today" for a UK business is a UK day, and for half the year UTC midnight
    | falls at one in the morning here - so a shift started at half past twelve
    | would be filed under yesterday.
    |
    */

    'timezone' => env('ATTENDANCE_TIMEZONE', 'Europe/London'),

    /*
    |--------------------------------------------------------------------------
    | Sessions per day
    |--------------------------------------------------------------------------
    |
    | A cap on how many times somebody may clock in and out in one day. Enough
    | for a lunch and something unexpected; low enough that a button pressed
    | repeatedly by accident does not fill the table.
    |
    */

    'max_sessions_per_day' => (int) env('ATTENDANCE_MAX_SESSIONS_PER_DAY', 6),

];
