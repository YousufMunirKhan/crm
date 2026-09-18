<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Everyone is asked for five leads a day and nobody was being told where they
 * stood, so the target existed only in conversation.
 *
 * The two emails are one set of figures - a rep's own, and the room's - and the
 * thing worth pinning down is that they cannot disagree, that a day is a UK day,
 * and that Sunday is not a day anybody missed a target on.
 */
class DailyLeadSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function user(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'email' => $email,
        ]);
    }

    private function leadsFor(User $user, int $count, ?string $at = null): void
    {
        // range(1, 0) counts down and hands back [1, 0], so "none" would be two.
        if ($count < 1) {
            return;
        }

        foreach (range(1, $count) as $n) {
            $customer = Customer::create([
                'name' => 'Shop '.random_int(1, 999999),
                'phone' => '07700900'.random_int(100, 999),
            ]);

            $lead = Lead::create([
                'customer_id' => $customer->id,
                'stage' => 'lead',
                'assigned_to' => $user->id,
            ]);

            if ($at) {
                $lead->forceFill(['created_at' => $at])->saveQuietly();
            }
        }
    }

    public function test_a_rep_is_told_how_far_off_the_target_they_are(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->leadsFor($rep, 2);

        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->hasTo('rep@example.com')
                && $m->mailData['count'] === 2
                && $m->mailData['target'] === 5
                && $m->mailData['short'] === 3;
        });
    }

    public function test_hitting_the_target_is_not_reported_as_a_shortfall(): void
    {
        $rep = $this->user('CallAgent', 'agent@example.com');
        $this->leadsFor($rep, 6);

        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->hasTo('agent@example.com') && $m->mailData['short'] === 0;
        });
    }

    public function test_management_gets_everybody_worst_first(): void
    {
        $busy = $this->user('Sales', 'busy@example.com');
        $quiet = $this->user('Sales', 'quiet@example.com');
        $this->user('Admin', 'boss@example.com');
        $this->leadsFor($busy, 4);
        $this->leadsFor($quiet, 0);

        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) use ($quiet) {
            if (! $m->hasTo('boss@example.com')) {
                return false;
            }

            return $m->mailData['rows'][0]['name'] === $quiet->name
                && $m->mailData['rows'][0]['short'] === 5
                && $m->mailData['teamCount'] === 4;
        });
    }

    public function test_a_rep_is_not_sent_the_whole_teams_figures(): void
    {
        $rep = $this->user('Sales', 'onlymine@example.com');
        $this->leadsFor($rep, 1);
        $other = $this->user('Sales', 'other@example.com');
        $this->leadsFor($other, 4);

        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return $m->hasTo('onlymine@example.com') && $m->mailData['count'] === 1;
        });
    }

    public function test_nothing_goes_out_on_a_sunday(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->leadsFor($rep, 1);

        $sunday = now(config('app.display_timezone'))->next('Sunday')->toDateString();

        $this->artisan('emails:daily-lead-summary', ['--date' => $sunday])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_goes_once_a_day(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->leadsFor($rep, 1);

        $this->artisan('emails:daily-lead-summary')->assertSuccessful();
        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
    }

    public function test_a_late_evening_lead_counts_for_the_uk_day_it_was_made_on(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $tz = config('app.display_timezone');
        $today = now($tz)->toDateString();

        // 23:30 UK. Stored in UTC that is 22:30 during British Summer Time, and
        // a naive whereDate() on the stored value still lands on the same day -
        // so the case that matters is the half hour after midnight UK.
        $this->leadsFor($rep, 1, now($tz)->setTime(23, 30)->utc()->toDateTimeString());

        $this->artisan('emails:daily-lead-summary', ['--date' => $today])->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('rep@example.com') && $m->mailData['count'] === 1);
    }

    public function test_sundays_are_left_out_of_the_chart(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');

        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            if (! $m->hasTo('rep@example.com')) {
                return false;
            }

            foreach ($m->mailData['days'] as $day) {
                if (\Carbon\Carbon::parse($day['date'])->isSunday()) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_both_emails_render(): void
    {
        $rep = $this->user('Sales', 'rep@example.com');
        $this->user('Admin', 'boss@example.com');
        $this->leadsFor($rep, 2);

        Mail::fake();
        $this->artisan('emails:daily-lead-summary')->assertSuccessful();

        // Mail::fake() never renders, so a broken Blade would pass every
        // assertion above and only fail once it was sent for real.
        foreach (Mail::sent(AutomatedEmail::class) as $mail) {
            // Rendering a layout-and-sections view leaves Blade's own output
            // buffer open, which PHPUnit reports as the test misbehaving. The
            // level is taken before and restored after so the warning belongs
            // to whoever actually earns it.
            $level = ob_get_level();
            $html = view($mail->mailView, $mail->mailData)->render();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            $this->assertStringContainsString('<table', $html);
            $this->assertStringNotContainsString('<svg', $html, 'Gmail strips SVG; the chart must be tables');
        }
    }

    public function test_the_summary_is_ten_oclock_uk(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'emails:daily-lead-summary'));

        $this->assertNotNull($event, 'the daily lead summary is not scheduled');
        $this->assertSame('0 10 * * *', $event->expression);
        $this->assertSame('Europe/London', (string) $event->timezone);
    }
}
