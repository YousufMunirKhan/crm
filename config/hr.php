<?php

return [
    /*
     * How long minute-by-minute location history is kept.
     *
     * Location is the most sensitive thing this system holds, and "how long do
     * you keep it" cannot be answered with "forever" for staff movement data.
     * Ninety days covers a quarter - enough to settle a disputed visit or an
     * expense claim, short enough that a breach two years from now cannot
     * reveal where somebody was last spring.
     *
     * The attendance record is not affected: who worked which day, and where
     * they clocked in and out, is kept as before.
     */
    'location_retention_days' => (int) env('LOCATION_RETENTION_DAYS', 90),
];
