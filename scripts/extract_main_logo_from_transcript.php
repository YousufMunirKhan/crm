<?php

/**
 * Pulls the first base64 <img> from the Cursor transcript (user's original welcome HTML)
 * and saves it as public/images/email/welcome/main-logo.png
 */

$transcript = 'C:\\Users\\Lenovo\\.cursor\\projects\\c-laragon-www-crm\\agent-transcripts\\5281d8a3-11ab-408d-9896-66119118f69f\\5281d8a3-11ab-408d-9896-66119118f69f.jsonl';
$outDir = dirname(__DIR__) . '/public/images/email/welcome';
$outFile = $outDir . '/main-logo.png';

if (! is_file($transcript)) {
    fwrite(STDERR, "Transcript not found: {$transcript}\n");
    exit(1);
}

$line = explode("\n", file_get_contents($transcript))[0];
$d = json_decode($line, true);
if (! is_array($d)) {
    fwrite(STDERR, "Invalid transcript JSON\n");
    exit(1);
}

$text = $d['message']['content'][0]['text'] ?? '';
if (! preg_match('/<img[^>]*src\s*=\s*"(data:image\/(png|jpeg|jpg|gif|webp);base64,([^"]+))"/i', $text, $m)) {
    fwrite(STDERR, "No base64 img in transcript\n");
    exit(1);
}

$ext = strtolower($m[2]) === 'jpeg' ? 'jpg' : strtolower($m[2]);
$raw = base64_decode($m[3], true);
if ($raw === false) {
    fwrite(STDERR, "Base64 decode failed\n");
    exit(1);
}

if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$target = $outDir . '/main-logo.' . $ext;
file_put_contents($target, $raw);

// Normalise name to main-logo.png when extension is png
if ($target !== $outFile && $ext === 'png') {
    @unlink($outFile);
    rename($target, $outFile);
}

echo "Wrote " . (is_file($outFile) ? $outFile : $target) . ' (' . strlen($raw) . " bytes)\n";
