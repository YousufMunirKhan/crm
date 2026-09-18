<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Takes a copy of the database and everything anybody has uploaded.
 *
 * There was no backup of any kind. Not a package, not a script, not a line in
 * the deployment notes. If the server had died the database and every file
 * would have gone with it: employment contracts, expense receipts, ticket
 * attachments, and the attendance photographs staff are required to take.
 *
 * Two deliberate choices.
 *
 * **It writes outside the web root.** `storage/` is reachable through the
 * public symlink; a backup archive containing the whole database sitting under
 * a URL would be a worse problem than the one it solves.
 *
 * **It is loud about what it does not protect against.** A copy on the same
 * disk survives a bad deploy, a dropped table and a deleted folder. It does not
 * survive the server. The command says so every run unless an off-box
 * destination is configured, because a backup people wrongly believe in is more
 * dangerous than none.
 */
class BackupSystem extends Command
{
    protected $signature = 'crm:backup
                            {--database-only : Skip the uploaded files}
                            {--keep= : Days of backups to keep, overriding config}';

    protected $description = 'Back up the database and uploaded files';

    public function handle(): int
    {
        $root = rtrim((string) config('backup.path'), '/');

        if ($root === '') {
            $this->error('No backup path configured. Set BACKUP_PATH.');

            return self::FAILURE;
        }

        if (! is_dir($root) && ! @mkdir($root, 0750, true)) {
            $this->error("Cannot create {$root}. Check the path and permissions.");

            return self::FAILURE;
        }

        if (! is_writable($root)) {
            $this->error("{$root} is not writable.");

            return self::FAILURE;
        }

        // Stamped from the app clock so the ordering matches the audit log.
        $stamp = now()->format('Y-m-d_His');
        $made = [];

        $database = $this->dumpDatabase($root, $stamp);

        if ($database === null) {
            return self::FAILURE;
        }

        $made[] = $database;

        if (! $this->option('database-only')) {
            $files = $this->archiveUploads($root, $stamp);

            if ($files !== null) {
                $made[] = $files;
            }
        }

        $this->prune($root);
        $this->offBoxWarning();

        foreach ($made as $path) {
            $this->info(sprintf('  %s  (%s)', basename($path), $this->humanSize(filesize($path))));
        }

        return self::SUCCESS;
    }

    /**
     * mysqldump, then verify the result actually contains a database.
     *
     * A dump that fails part way still leaves a file, and a zero-length or
     * truncated archive that nobody checks is the classic way a backup regime
     * turns out to have been decorative all along.
     */
    private function dumpDatabase(string $root, string $stamp): ?string
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $path = "{$root}/db_{$stamp}.sql.gz";

        $process = Process::fromShellCommandline(
            'mysqldump --single-transaction --quick --no-tablespaces '
            .'--host=${:DB_HOST} --port=${:DB_PORT} --user=${:DB_USER} ${:DB_NAME} '
            .'| gzip > ${:OUT}'
        );

        $process->setTimeout(1800);
        $process->setEnv(['MYSQL_PWD' => (string) $db['password']]);
        $process->run(null, [
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_USER' => $db['username'],
            'DB_NAME' => $db['database'],
            'OUT' => $path,
        ]);

        if (! $process->isSuccessful()) {
            $this->error('Database dump failed: '.trim($process->getErrorOutput()));
            @unlink($path);

            return null;
        }

        // A gzip of nothing is ~20 bytes; a real dump of this schema is megabytes.
        if (! file_exists($path) || filesize($path) < 1024) {
            $this->error('The dump came out empty. Nothing was backed up.');
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function archiveUploads(string $root, string $stamp): ?string
    {
        $source = storage_path('app/public');

        if (! is_dir($source)) {
            $this->warn('No uploads directory to archive.');

            return null;
        }

        $path = "{$root}/files_{$stamp}.tar.gz";

        $process = Process::fromShellCommandline('tar -czf ${:OUT} -C ${:DIR} .');
        $process->setTimeout(1800);
        $process->run(null, ['OUT' => $path, 'DIR' => $source]);

        if (! $process->isSuccessful()) {
            $this->error('File archive failed: '.trim($process->getErrorOutput()));
            @unlink($path);

            return null;
        }

        return $path;
    }

    /**
     * Delete old archives, keeping at least the most recent pair whatever the
     * retention says - a misconfigured retention must not leave nothing.
     */
    private function prune(string $root): void
    {
        $days = (int) ($this->option('keep') ?? config('backup.keep_days', 14));
        $cutoff = now()->subDays(max(1, $days))->getTimestamp();

        $files = glob("{$root}/{db,files}_*.{sql.gz,tar.gz}", GLOB_BRACE) ?: [];

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $removed = 0;

        foreach (array_slice($files, 2) as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line("Removed {$removed} archive(s) older than {$days} days.");
        }
    }

    private function offBoxWarning(): void
    {
        if (config('backup.offsite_disk')) {
            return;
        }

        $this->warn(
            'These copies are on the same machine as the data. They protect against a bad '
            .'deploy or a dropped table - not against losing the server. Set BACKUP_OFFSITE_DISK, '
            .'or pull them somewhere else on a schedule.'
        );
    }

    private function humanSize(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
