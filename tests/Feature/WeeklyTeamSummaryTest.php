<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\HR\Models\EmployeeTarget;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A day on its own says almost nothing - one quiet Tuesday is weather, not a
 * pattern. The week is the unit somebody can act on, and Sunday is when there
 * is time to read a table rather than glance at a number.
 */
class WeeklyTeamSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function user(string $roleName, string $email, ?int $dailyLeads = 5): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'email' => $email]);

        if ($dailyLeads !== null) {
            EmployeeTarget::create([
                'user_id' => $user->id,
                'month' => now(config('app.display_timezone'))->format('Y-m'),
                'target_appointments' => 10,
                'target_sales' => 5,
                'target_daily_leads' => $dailyLeads,
            ]);
        }

        return $user;
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
            'created_at' => \Carbon\Carbon::parse($date, config('app.display_timezone'))->setTime(11, 0)->utc(),
        ])->saveQuietly();
    }

    /** The Sunday that follows the week we are filling in. */
    private function sunday(): \Carbon\Carbon
    {
        return now(config('app.display_timezone'))->startOfWeek(\Carbon\Carbon::MONDAY)->addDays(6);
    }

    public function test_the_week_it_reports_is_monday_to_saturday(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $sunday = $this->sunday();
        $monday = $sunday->copy()->subWeek()->startOfWeek(\Carbon\Carbon::MONDAY);

        $this->leadOn($rep, $monday->toDateString());
        $this->leadOn($rep, $monday->copy()->addDays(5)->toDateString());   // Saturday
        $this->leadOn($rep, $monday->copy()->addDays(6)->toDateString());   // Sunday, not counted

        $this->artisan('emails:weekly-team-summary', ['--date' => $sunday->toDateString()])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->hasTo('boss@example.com')
                && $m->mailData['teamLeads'] === 2
                && count($m->mailData['days']) === 6;
        });
    }

    public function test_the_week_target_is_the_daily_one_across_six_days(): void
    {
        $this->user('Sales', 'rep@example.com', dailyLeads: 3);
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => $this->sunday()->toDateString()])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com')
            && $m->mailData['teamLeadTarget'] === 18);
    }

    public function test_it_goes_to_management_and_not_to_the_team(): void
    {
        $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);
        $this->user('Manager', 'manager@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => $this->sunday()->toDateString()])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('boss@example.com'));
        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('manager@example.com'));
        Mail::assertNotSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('rep@example.com'));
    }

    public function test_everybody_on_a_target_is_named_furthest_behind_first(): void
    {
        $busy = $this->user('Sales', 'busy@example.com');
        $quiet = $this->user('CallAgent', 'quiet@example.com');
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $monday = $this->sunday()->copy()->subWeek()->startOfWeek(\Carbon\Carbon::MONDAY);
        foreach (range(0, 4) as $offset) {
            $this->leadOn($busy, $monday->copy()->addDays($offset)->toDateString());
        }

        $this->artisan('emails:weekly-team-summary', ['--date' => $this->sunday()->toDateString()])->assertSuccessful();

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
        $sunday = $this->sunday()->toDateString();

        $this->artisan('emails:weekly-team-summary', ['--date' => $sunday])->assertSuccessful();
        $this->artisan('emails:weekly-team-summary', ['--date' => $sunday])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
    }

    public function test_nobody_on_a_target_means_nothing_is_sent(): void
    {
        $this->user('Admin', 'boss@example.com', dailyLeads: null);

        $this->artisan('emails:weekly-team-summary', ['--date' => $this->sunday()->toDateString()])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_is_scheduled_for_sunday_morning_uk(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'emails:weekly-team-summary'));

        $this->assertNotNull($event, 'the weekly team summary is not scheduled');
        $this->assertSame('0 10 * * 0', $event->expression);
        $this->assertSame('Europe/London', (string) $event->timezone);
    }
}
