<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs the scheduler when something outside asks it to.
 *
 * There is no cron on this host - no binary, no spool, because the plan sets it
 * from a control panel and nowhere else. Nothing scheduled has ever run here,
 * which is why abandoned shifts piled up, invoices were never marked overdue,
 * and the ticket SLA check has never fired once.
 *
 * So an outside pinger calls this every minute instead. What it runs is fixed:
 * schedule:run, which works out from routes/console.php what is due. Nothing
 * from the request reaches a command.
 *
 * The token is compared with hash_equals rather than == so the comparison does
 * not leak its own answer through how long it takes, and a missing token means
 * the route 404s rather than reporting itself as switched off - an endpoint
 * that runs the application's jobs should not confirm it exists to somebody
 * guessing.
 */
class SchedulerRunController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $expected = (string) config('scheduler.http_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $started = microtime(true);

        Artisan::call('schedule:run');

        $output = trim(Artisan::output());
        $seconds = round(microtime(true) - $started, 2);

        // Only when something actually ran. A minute where nothing was due is
        // the normal case and would otherwise fill the log sixty times an hour.
        if ($output !== '' && ! str_contains($output, 'No scheduled commands are ready')) {
            Log::info('Scheduler ran over HTTP in '.$seconds.'s: '.$output);
        }

        return response()->json([
            'ran' => true,
            'seconds' => $seconds,
        ]);
    }
}
