<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerHttpRunTest extends TestCase
{
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
}
