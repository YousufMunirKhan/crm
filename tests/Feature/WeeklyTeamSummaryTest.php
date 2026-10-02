<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\HR\Models\EmployeeTarget;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A day on its own says almost nothing - one quiet Tuesday is weather, not a
 * pattern. The week is the unit somebody can act on, and Sunday is when there
 * is time to read a table rather than glance at a number.
 *
 * The clock is pinned. These used to work out "this Sunday" from the real date
 * and give people a target for the real month, which held until the week being
 * reported fell in the month before - so they failed for the first few days of
 * every month, and hid that the week being reported was the wrong one.
 */
class WeeklyTeamSummaryTest extends TestCase
{
    use RefreshDatabase;

    /** A Sunday in the middle of a month, and the week it closes. */
    private const SUNDAY = '2026-09-20';

    private const MONDAY = '2026-09-14';

    private const SATURDAY = '2026-09-19';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->sundayNight(self::SUNDAY);
    }

    /** Ten at night UK time, which is when the schedule runs it. */
    private function sundayNight(string $date): void
    {
        $this->travelTo(Carbon::parse($date.' 22:00', config('app.display_timezone')));
    }

    private function user(string $roleName, string $email, ?int $dailyLeads = 5, string $month = '2026-09'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'email' => $email]);

        if ($dailyLeads !== null) {
            $this->target($user, $month, $dailyLeads);
        }

        return $user;
    }

    private function target(User $user, string $month, int $dailyLeads): void
    {
        EmployeeTarget::create([
            'user_id' => $user->id,
            'month' => $month,
            'target_appointments' => 10,
            'target_sales' => 5,
            'target_daily_leads' => $dailyLeads,
        ]);
    }

    private function leadOn(User $user, string $date): void
    {
        $customer = Customer::create([
            'name' => 'Shop '.random_int(1, 999999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $user->id,
        ])->forceFill([
            'created_at' => Carbon::parse($date, config('app.display_timezone'))->setTime(11, 0)->utc(),
        ])->saveQuietly();
    }

    public function test_the_week_it_reports_is_monday_to_saturday(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->leadOn($rep, self::MONDAY);
        $this->leadOn($rep, self::SATURDAY);
        $this->leadOn($rep, self::SUNDAY);   // not a working day, not counted

        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->hasTo('boss@example.com')
                && $m->mailData['teamLeads'] === 2
                && array_column($m->mailData['days'], 'date') === [
                    '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19',
                ];
        });
    }

    /**
     * Sent on Sunday 20 September, it used to describe 7 to 12 September: the
     * week before the one that had just ended, under a heading saying otherwise.
     */
    public function test_the_week_is_the_one_that_has_just_ended_not_the_one_before(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->leadOn($rep, '2026-09-08');
        $this->leadOn($rep, '2026-09-12');
        $this->leadOn($rep, '2026-09-16');

        // No date given, as the schedule runs it.
        $this->artisan('emails:weekly-team-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com')
            && $m->mailSubject === 'Team week: 14 Sep to 19 Sep'
            && $m->mailData['weekLabel'] === '14 September to 19 September 2026'
            && $m->mailData['teamLeads'] === 1);
    }

    /** A preview on a Wednesday is of a finished week, not half of this one. */
    public function test_run_midweek_it_still_reports_the_last_finished_week(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->leadOn($rep, self::MONDAY);
        $this->leadOn($rep, '2026-09-21');
        $this->leadOn($rep, '2026-09-22');

        foreach (['2026-09-21', '2026-09-23', '2026-09-26'] as $day) {
            $this->artisan('emails:weekly-team-summary', ['--date' => $day, '--dry-run' => true])->assertSuccessful();
        }

        $this->artisan('emails:weekly-team-summary', ['--date' => '2026-09-23'])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com')
            && $m->mailSubject === 'Team week: 14 Sep to 19 Sep'
            && $m->mailData['teamLeads'] === 1);
    }

    public function test_the_week_target_is_the_daily_one_across_six_days(): void
    {
        $this->user('Sales', 'rep@example.com', dailyLeads: 3);
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com')
            && $m->mailData['teamLeadTarget'] === 18);
    }

    /**
     * Sunday 1 November closes a week that sat entirely in October. Nobody has
     * been given a November number yet, and nobody needs one to be told how
     * October's last week went.
     */
    public function test_a_week_reported_on_the_first_of_a_month_uses_the_month_it_was_worked_in(): void
    {
        $this->sundayNight('2026-11-01');

        $rep = $this->user('Sales', 'rep@example.com', dailyLeads: 4, month: '2026-10');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->leadOn($rep, '2026-10-26');
        $this->leadOn($rep, '2026-10-31');

        $this->artisan('emails:weekly-team-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com')
            && $m->mailSubject === 'Team week: 26 Oct to 31 Oct'
            && $m->mailData['teamLeads'] === 2
            && $m->mailData['teamLeadTarget'] === 24
            && $m->mailData['monthLabel'] === 'October');
    }

    /**
     * Monday 28 September to Saturday 3 October was worked under two months'
     * numbers. It is measured on the month it ended in, and the days before
     * the month turned still count towards it.
     */
    public function test_a_week_across_two_months_is_measured_on_the_one_it_ends_in(): void
    {
        $this->sundayNight('2026-10-04');

        $rep = $this->user('Sales', 'rep@example.com', dailyLeads: 5, month: '2026-09');
        $this->target($rep, '2026-10', 3);
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->leadOn($rep, '2026-09-28');
        $this->leadOn($rep, '2026-10-03');

        $this->artisan('emails:weekly-team-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com')
            && $m->mailSubject === 'Team week: 28 Sep to 3 Oct'
            && $m->mailData['teamLeads'] === 2
            && $m->mailData['teamLeadTarget'] === 18
            && $m->mailData['monthLabel'] === 'October');
    }

    public function test_it_goes_to_management_and_not_to_the_team(): void
    {
        $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);
        $this->user('Manager', 'manager@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com'));
        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('manager@example.com'));
        Mail::assertNotSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('rep@example.com'));
    }

    public function test_everybody_on_a_target_is_named_furthest_behind_first(): void
    {
        $busy = $this->user('Sales', 'busy@example.com');
        $quiet = $this->user('CallAgent', 'quiet@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        foreach (range(0, 4) as $offset) {
            $this->leadOn($busy, Carbon::parse(self::MONDAY)->addDays($offset)->toDateString());
        }

        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) use ($quiet, $busy) {
            if (! $m->hasTo('boss@example.com')) {
                return false;
            }

            $names = array_column($m->mailData['rows'], 'name');

            return $names === [$quiet->name, $busy->name];
        });
    }

    public function test_it_goes_once_a_week(): void
    {
        $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();
        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
    }

    public function test_nobody_on_a_target_means_nothing_is_sent(): void
    {
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => self::SUNDAY])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_is_scheduled_for_sunday_night_uk(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'emails:weekly-team-summary'));

        $this->assertNotNull($event, 'the weekly team summary is not scheduled');
        $this->assertSame('0 22 * * 0', $event->expression);
        $this->assertSame('Europe/London', (string) $event->timezone);
    }
}
