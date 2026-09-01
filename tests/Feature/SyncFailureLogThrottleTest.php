<?php

namespace Tests\Feature;

use App\Modules\Communication\Services\WhatsAppTemplateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The WhatsApp sync runs every fifteen minutes. Its token expired on 2 April
 * and has been rejected on every run since, filling the production log with the
 * same OAuthException - about fifteen thousand lines by September, which is
 * where a genuinely new error goes to hide.
 *
 * The throttle added for that hashed the message as it arrived, and Meta puts
 * the current time and a fresh trace id inside the message text. So every
 * attempt produced a different key, nothing was ever suppressed, and the log
 * kept filling at four lines an hour while the fix looked like it worked. This
 * is the test that would have said so.
 */
class SyncFailureLogThrottleTest extends TestCase
{
    private function metaError(string $when, string $trace): string
    {
        return 'WhatsApp API HTTP 401 | type=OAuthException | code=190 | subcode=463 | '
            .'message=Error validating access token: Session has expired on Thursday, 02-Apr-26 14:00:00 PDT. '
            ."The current time is {$when}. | fbtrace_id={$trace}";
    }

    private function callLogger(string $message): void
    {
        $service = app(WhatsAppTemplateService::class);

        $method = new \ReflectionMethod($service, 'logSyncFailure');
        $method->setAccessible(true);
        $method->invoke($service, $message);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_the_same_failure_is_logged_once_however_the_clock_moves(): void
    {
        Log::spy();

        $this->callLogger($this->metaError('Tuesday, 01-Sep-26 06:16:02 PDT', 'A80vt-Z-H767ItZBRQed90T'));
        $this->callLogger($this->metaError('Tuesday, 01-Sep-26 06:31:01 PDT', 'A1ksY9cWR2pVuSCC5O5KTwI'));
        $this->callLogger($this->metaError('Tuesday, 01-Sep-26 06:46:02 PDT', 'AECOjVBHBlmRci9Jjx24zIF'));

        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_different_failure_is_still_reported(): void
    {
        Log::spy();

        $this->callLogger($this->metaError('Tuesday, 01-Sep-26 06:16:02 PDT', 'A80vt'));

        // A second, unrelated fault must appear beside the first rather than
        // underneath it - that is the entire reason for throttling the first.
        $this->callLogger('WhatsApp API HTTP 500 | type=OAuthException | code=2 | message=Service temporarily unavailable');

        Log::shouldHaveReceived('error')->twice();
    }

    public function test_the_error_code_still_tells_two_failures_apart(): void
    {
        Log::spy();

        $this->callLogger('WhatsApp API HTTP 401 | code=190 | message=Session has expired on 02-Apr-26 14:00:00');
        $this->callLogger('WhatsApp API HTTP 401 | code=100 | message=Session has expired on 02-Apr-26 14:00:00');

        // Stripping every digit would have collapsed these into one entry.
        Log::shouldHaveReceived('error')->twice();
    }

    public function test_it_speaks_again_once_the_hour_is_up(): void
    {
        Log::spy();

        $message = $this->metaError('Tuesday, 01-Sep-26 06:16:02 PDT', 'A80vt');

        $this->callLogger($message);
        $this->travel(61)->minutes();
        $this->callLogger($message);

        // Silence forever would be its own bug: an expired credential is a real
        // failure and should keep saying so.
        Log::shouldHaveReceived('error')->twice();
    }
}
