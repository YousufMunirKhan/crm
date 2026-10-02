<?php

/**
 * Cold calling sweep runner.
 *
 * The CRM decides which areas to sweep (Cold calling → Around our customers)
 * but cannot run the Google Maps scraper itself: the scraper is a Docker
 * container and the live host has no Docker. So this runs on an office PC.
 * It asks the CRM for the next queued area, has the local scraper search
 * Google Maps there, and posts the listings back - until the queue is empty.
 *
 *   php scripts/gmaps-sweep/sweep.php            every queued area
 *   php scripts/gmaps-sweep/sweep.php --max=3    stop after three
 *
 * Needs only PHP with curl - no Laravel, no database. Settings come from the
 * .env beside this file (see .env.example). The scraper is the open-source
 * gosom/google-maps-scraper, set up as in
 * https://github.com/Mahanaicoach/google-maps-scraper-kit.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

const ROWS_PER_POST = 100;
const POLL_SECONDS = 10;

// What the CRM keeps of a listing. The scraper's other columns (review text,
// image lists, popular times) are megabytes per area and are not sent.
const COLUMNS = [
    'input_id', 'link', 'title', 'category', 'address', 'open_hours', 'website', 'phone',
    'review_count', 'review_rating', 'latitude', 'longitude', 'cid', 'status', 'descriptions',
    'price_range', 'place_id', 'complete_address', 'emails',
];

$settings = settings(__DIR__.'/.env');
$crm = rtrim($settings['CRM_URL'] ?? '', '/');
$key = $settings['CRM_KEY'] ?? '';
$scraper = rtrim($settings['SCRAPER_URL'] ?? 'http://127.0.0.1:8080', '/');
$pause = max(0, (int) ($settings['PAUSE_SECONDS'] ?? 30));
$proxies = array_values(array_filter(array_map('trim', explode(',', $settings['PROXIES'] ?? ''))));

$max = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--max=(\d+)$/', $arg, $m)) {
        $max = (int) $m[1];
    }
}

if ($crm === '' || $key === '') {
    fail('Set CRM_URL and CRM_KEY in '.__DIR__.DIRECTORY_SEPARATOR.'.env (copy .env.example).');
}

ensureScraperIsUp($scraper);

$done = 0;
$emptyInARow = 0;

while ($max === 0 || $done < $max) {
    [$status, $claim] = crm($crm, $key, '/claim');
    if ($status === 401 || $status === 503) {
        fail('The CRM refused the key: '.($claim['error'] ?? "HTTP $status"));
    }
    if ($status !== 200) {
        fail("The CRM did not answer the claim (HTTP $status).");
    }

    $run = $claim['run'] ?? null;
    if ($run === null) {
        say($done === 0 ? 'Nothing is queued. Queue areas in the CRM: Cold calling → Around our customers.' : 'Queue is empty.');
        break;
    }

    if ($done > 0 && $pause > 0) {
        say("Pausing {$pause}s before the next area…");
        sleep($pause);
    }

    $done++;
    say(sprintf('▶ %s — %d search(es), depth %d%s', $run['label'], count($run['keywords']), $run['depth'], $run['email'] ? ', with emails' : ''));

    try {
        $rows = scrape($scraper, $run, $proxies);
    } catch (RuntimeException $e) {
        say('  ✗ '.$e->getMessage());
        crm($crm, $key, "/runs/{$run['id']}/finish", ['status' => 'failed', 'error_message' => $e->getMessage()]);
        $rows = null;
    }

    if ($rows !== null && $rows !== []) {
        $emptyInARow = 0;
        $new = $duplicate = $skipped = 0;

        foreach (array_chunk($rows, ROWS_PER_POST) as $chunk) {
            [$status, $body] = crm($crm, $key, "/runs/{$run['id']}/rows", ['rows' => $chunk]);
            if ($status !== 200) {
                say("  ✗ The CRM rejected a batch (HTTP $status). ".($body['error'] ?? $body['message'] ?? ''));

                continue;
            }
            $new += $body['counts']['new'];
            $duplicate += $body['counts']['duplicate'];
            $skipped += $body['counts']['skipped'];
        }

        crm($crm, $key, "/runs/{$run['id']}/finish", ['status' => 'completed', 'rows_scraped' => count($rows)]);
        say(sprintf('  ✓ %d listings: %d new, %d already saved, %d filtered out.', count($rows), $new, $duplicate, $skipped));

        continue;
    }

    if ($rows === []) {
        $message = 'The scraper found nothing here. Google may be rate-limiting this PC - try again in a few hours.';
        say('  ✗ '.$message);
        crm($crm, $key, "/runs/{$run['id']}/finish", ['status' => 'failed', 'error_message' => $message, 'rows_scraped' => 0]);
    }

    // Two areas in a row with nothing is what a block looks like. Carrying on
    // would burn the rest of the queue and keep the block alive.
    if (++$emptyInARow >= 2) {
        say('Stopping: two areas in a row failed. The rest of the queue is untouched - run this again later.');
        break;
    }
}

say("Done. $done area(s) processed.");

// ---------------------------------------------------------------------------

/**
 * One area through the scraper: create the job, wait, download.
 *
 * @return list<array<string, string>>
 */
function scrape(string $scraper, array $run, array $proxies): array
{
    $job = [
        'name' => 'crm-sweep-'.$run['id'],
        'keywords' => $run['keywords'],
        'lang' => 'en',
        'zoom' => 15,
        // The scraper's API wants these as strings, and max_time in seconds.
        'lat' => (string) $run['lat'],
        'lon' => (string) $run['lon'],
        'fast_mode' => false,
        'radius' => (int) $run['radius'],
        'depth' => (int) $run['depth'],
        'email' => (bool) $run['email'],
        'max_time' => (int) $run['max_time'],
    ];
    if ($proxies !== []) {
        $job['proxies'] = $proxies;
    }

    [$status, $body] = http('POST', $scraper.'/api/v1/jobs', $job);
    $created = json_decode($body, true);
    $jobId = is_array($created) ? ($created['id'] ?? null) : null;
    if ($status >= 300 || ! is_string($jobId)) {
        throw new RuntimeException("The scraper would not start the job (HTTP $status): ".substr($body, 0, 200));
    }

    $deadline = time() + (int) $run['max_time'] + 180;
    $state = '';
    while (time() < $deadline) {
        sleep(POLL_SECONDS);
        [, $body] = http('GET', $scraper.'/api/v1/jobs/'.$jobId);
        $info = json_decode($body, true);
        $state = is_array($info) ? (string) ($info['Status'] ?? $info['status'] ?? '') : '';
        echo "\r  scraping… ".str_pad($state, 10).' '.date('H:i:s');
        if ($state === 'ok' || $state === 'failed') {
            break;
        }
    }
    echo "\n";

    if ($state !== 'ok') {
        throw new RuntimeException($state === 'failed'
            ? 'The scraper job failed. If it keeps happening, Google is rate-limiting this PC - wait a few hours.'
            : 'The scraper did not finish in time.');
    }

    [$status, $csv] = http('GET', $scraper.'/api/v1/jobs/'.$jobId.'/download');
    if ($status !== 200) {
        throw new RuntimeException("Could not download the results (HTTP $status).");
    }

    // The results are in the CRM from here on; do not let them pile up on disk.
    http('DELETE', $scraper.'/api/v1/jobs/'.$jobId);

    return parseCsv($csv);
}

/**
 * @return list<array<string, string>>
 */
function parseCsv(string $csv): array
{
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $header = fgetcsv($handle, null, ',', '"', '');
    $rows = [];
    if (is_array($header)) {
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $row = [];
            foreach ($header as $i => $column) {
                if (in_array($column, COLUMNS, true)) {
                    $row[$column] = (string) ($line[$i] ?? '');
                }
            }
            if (($row['title'] ?? '') !== '') {
                $rows[] = $row;
            }
        }
    }
    fclose($handle);

    return $rows;
}

function ensureScraperIsUp(string $scraper): void
{
    if (scraperAnswers($scraper)) {
        return;
    }

    say('The scraper is not running - starting it (docker compose up -d)…');
    passthru('docker compose -f '.escapeshellarg(__DIR__.DIRECTORY_SEPARATOR.'docker-compose.yml').' up -d', $exit);
    if ($exit !== 0) {
        fail('Could not start the scraper. Is Docker Desktop running?');
    }

    // First start downloads a browser inside the container.
    for ($i = 0; $i < 30; $i++) {
        sleep(4);
        if (scraperAnswers($scraper)) {
            return;
        }
    }

    fail("The scraper container started but $scraper is not answering.");
}

function scraperAnswers(string $scraper): bool
{
    try {
        [$status] = http('GET', $scraper.'/api/v1/jobs', null, [], 5);

        return $status === 200;
    } catch (RuntimeException) {
        return false;
    }
}

/**
 * @return array{0: int, 1: array<string, mixed>}
 */
function crm(string $crm, string $key, string $path, array $body = []): array
{
    [$status, $raw] = http('POST', $crm.'/api/cold-calling-runner'.$path, $body, ['X-Api-Key: '.$key, 'Accept: application/json'], 120);
    $decoded = json_decode($raw, true);

    return [$status, is_array($decoded) ? $decoded : []];
}

/**
 * @return array{0: int, 1: string}
 */
function http(string $method, string $url, ?array $json = null, array $headers = [], int $timeout = 60): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge($headers, $json !== null ? ['Content-Type: application/json'] : []),
    ];
    if ($json !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    }
    // Windows PHP often ships without a CA bundle; use the system's certificates.
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("Could not reach $url: $error");
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, (string) $body];
}

/**
 * @return array<string, string>
 */
function settings(string $file): array
{
    $settings = [];
    foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
        if (preg_match('/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/', $line, $m)) {
            $settings[$m[1]] = trim($m[2], "\"'");
        }
    }
    foreach (['CRM_URL', 'CRM_KEY', 'SCRAPER_URL', 'PAUSE_SECONDS', 'PROXIES'] as $name) {
        if (getenv($name) !== false && getenv($name) !== '') {
            $settings[$name] = getenv($name);
        }
    }

    return $settings;
}

function say(string $line): void
{
    echo $line."\n";
}

function fail(string $line): never
{
    fwrite(STDERR, '✗ '.$line."\n");
    exit(1);
}
