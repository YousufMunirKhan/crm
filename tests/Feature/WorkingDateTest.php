<?php

namespace Tests\Feature;

use App\Modules\HR\Models\Attendance;
use Tests\TestCase;

class WorkingDateTest extends TestCase
{
    public function test_the_working_day_follows_the_uk_not_utc(): void
    {
        // Half past midnight in London on 11 June, which is still 10 June in UTC
        // because British Summer Time is an hour ahead. A shift starting here
        // was being filed under the wrong day.
        $this->travelTo('2026-06-10 23:30:00');

        $this->assertSame('2026-06-10', now()->toDateString());
        $this->assertSame('2026-06-11', Attendance::workingDate());
    }

    public function test_in_winter_the_two_agree(): void
    {
        // GMT: London and UTC are the same clock, so nothing shifts.
        $this->travelTo('2026-01-15 23:30:00');

        $this->assertSame('2026-01-15', now()->toDateString());
        $this->assertSame('2026-01-15', Attendance::workingDate());
    }

    public function test_the_ordinary_middle_of_the_day_is_unaffected(): void
    {
        $this->travelTo('2026-06-10 11:00:00');

        $this->assertSame('2026-06-10', Attendance::workingDate());
    }
}
