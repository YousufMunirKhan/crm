<?php

namespace Tests\Feature;

use App\Modules\Settings\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerHttpRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_right_token_runs_the_scheduler(): void
    {
        config(['scheduler.http_token' => 'a-long-secret-value']);

        $this->get('/scheduler/run/a-long-secret-value')
            ->assertOk()
            ->assertJsonPath('ran', true);
    }

    public function test_a_wrong_token_is_not_told_that_it_is_wrong(): void
    {
        config(['scheduler.http_token' => 'a-long-secret-value']);

        // 404, not 403. A 403 confirms the endpoint exists to anybody guessing.
        $this->get('/scheduler/run/wrong')->assertNotFound();
    }

    public function test_with_no_token_configured_the_route_does_not_exist(): void
    {
        config(['scheduler.http_token' => '']);

        // Only the tokened form. /scheduler/run/ with nothing after it does not
        // match this route at all and falls through to the SPA shell, which is
        // the app's front page and not the scheduler.
        $this->get('/scheduler/run/anything')->assertNotFound();
    }

    public function test_an_empty_token_cannot_be_matched_by_an_empty_guess(): void
    {
        config(['scheduler.http_token' => '']);

        // The guard is on the configured value being empty, not on the two
        // happening to be equal - otherwise switching it off would open it up.
        $this->get('/scheduler/run/0')->assertNotFound();
    }

    public function test_nothing_from_the_url_reaches_a_command(): void
    {
        config(['scheduler.http_token' => 'a-long-secret-value']);

        $ran = [];
        Artisan::command('test:marker', function () use (&$ran) {
            $ran[] = 'test:marker';
        });

        $this->get('/scheduler/run/a-long-secret-value')->assertOk();

        // Only schedule:run is invoked; the token is a credential, not input.
        $this->assertSame([], $ran);
    }

    public function test_a_run_leaves_a_heartbeat_so_a_dead_pinger_is_visible(): void
    {
        config(['scheduler.http_token' => 'a-long-secret-value']);

        $this->assertNull(Setting::where('key', 'scheduler_last_run_at')->first());

        $this->get('/scheduler/run/a-long-secret-value')->assertOk();

        // Production runs at LOG_LEVEL=error, so the log line this used to rely
        // on is dropped - an endpoint nobody is calling looked exactly like one
        // that is working.
        $this->assertNotNull(Setting::where('key', 'scheduler_last_run_at')->first()?->value);
    }

    public function test_a_refused_run_leaves_no_heartbeat(): void
    {
        config(['scheduler.http_token' => 'a-long-secret-value']);

        $this->get('/scheduler/run/wrong')->assertNotFound();

        $this->assertNull(Setting::where('key', 'scheduler_last_run_at')->first());
    }

    public function test_the_queue_is_drained_by_the_scheduler_when_there_is_no_worker(): void
    {
        // This host cannot keep `queue:work` alive, so the schedule drains the
        // queue itself. On a sync connection there is nothing to drain.
        $this->assertFalse($this->scheduledCommandsFor('sync')->contains(fn ($c) => str_contains($c, 'queue:work')));
        $this->assertTrue($this->scheduledCommandsFor('database')->contains(fn ($c) => str_contains($c, 'queue:work')));
    }

    /** The commands routes/console.php registers under a given queue connection. */
    private function scheduledCommandsFor(string $connection)
    {
        config(['queue.default' => $connection]);

        $schedule = new Schedule();
        $this->app->instance(Schedule::class, $schedule);
        // The facade holds on to whatever it resolved first, so rebinding alone
        // would leave routes/console.php registering against the real schedule.
        \Illuminate\Support\Facades\Schedule::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        return collect($schedule->events())->map(fn ($e) => $e->command ?? '');
    }
}
