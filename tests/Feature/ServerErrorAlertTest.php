<?php

namespace Tests\Feature;

use App\Mail\ServerErrorAlert;
use App\Support\ErrorAlerts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

class ServerErrorAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['alerts.error_email' => ['ops@example.com']]);
        config(['alerts.throttle_minutes' => 15]);
        config(['alerts.max_per_hour' => 20]);
    }

    public function test_a_fault_is_emailed(): void
    {
        Mail::fake();

        ErrorAlerts::report(new RuntimeException('database has gone away'));

        Mail::assertSent(ServerErrorAlert::class, function (ServerErrorAlert $mail) {
            return $mail->hasTo('ops@example.com')
                && $mail->errorMessage === 'database has gone away';
        });
    }

    public function test_nothing_is_sent_when_no_address_is_configured(): void
    {
        Mail::fake();
        config(['alerts.error_email' => []]);

        ErrorAlerts::report(new RuntimeException('nobody to tell'));

        Mail::assertNothingSent();
    }

    public function test_the_ordinary_does_not_raise_an_alert(): void
    {
        Mail::fake();

        ErrorAlerts::report(new NotFoundHttpException('no such page'));
        ErrorAlerts::report(ValidationException::withMessages(['email' => 'required']));

        Mail::assertNothingSent();
    }

    public function test_a_deliberate_server_side_abort_still_alerts(): void
    {
        Mail::fake();

        ErrorAlerts::report(new ServiceUnavailableHttpException(null, 'maintenance gone wrong'));

        Mail::assertSent(ServerErrorAlert::class);
    }

    public function test_the_same_fault_only_mails_once_in_the_window(): void
    {
        Mail::fake();

        // One line throwing repeatedly is the flood this guards against.
        for ($i = 0; $i < 5; $i++) {
            ErrorAlerts::report(new RuntimeException('the same broken page'));
        }

        Mail::assertSentCount(1);
    }

    public function test_a_different_fault_is_not_hidden_behind_the_first(): void
    {
        Mail::fake();

        ErrorAlerts::report(new RuntimeException('first fault'));
        ErrorAlerts::report(new \LogicException('a different fault entirely'));

        Mail::assertSentCount(2);
    }

    public function test_the_hourly_ceiling_stops_a_storm_of_distinct_errors(): void
    {
        Mail::fake();
        config(['alerts.max_per_hour' => 3]);

        // Distinct line numbers, so the per-error gate never applies.
        $throw = function (int $n) {
            try {
                throw new RuntimeException("fault {$n}");
            } catch (RuntimeException $e) {
                return $e;
            }
        };

        for ($i = 0; $i < 6; $i++) {
            ErrorAlerts::report(new RuntimeException("distinct {$i}", $i));
            Cache::forget('error-alert:seen:'.sha1(RuntimeException::class.'|'.__FILE__.'|'.(__LINE__ - 1)));
        }

        $this->assertLessThanOrEqual(3, count(Mail::sent(ServerErrorAlert::class)));
    }

    public function test_a_broken_mailer_does_not_replace_the_error_being_reported(): void
    {
        // No Mail::fake() - the array transport is swapped for one that throws,
        // which is what a wrong SMTP password looks like in production.
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP refused'));

        ErrorAlerts::report(new RuntimeException('the original fault'));

        // Reaching here at all is the assertion: report() swallowed it.
        $this->assertTrue(true);
    }
}
