<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeRequestLogs extends Command
{
    protected $signature = 'logs:purge-requests
                            {--days= : Override retention days (default from config)}
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation}';

    protected $description = 'Purge old request log files (raw bodies + metadata)';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null
            ? (int) $daysOption
            : (int) config('logging.request.retention_days', 14);

        $days = max(0, $days);
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $disk = Storage::disk('request_logs');
        $root = $disk->path('');

        if (! is_dir($root)) {
            $this->info('No request logs directory found. Nothing to purge.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->startOfDay();

        $this->info("Purging request logs older than {$cutoff->toDateString()} (retention: {$days} days)");
        $this->line("Root: {$root}");

        if ($dryRun) {
            $this->warn('DRY-RUN mode – no files will be deleted.');
        }

        if (! $dryRun && ! $force && ! $this->confirm('Continue?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deletedDirs = 0;
        $freedBytes  = 0;

        foreach (scandir($root) ?: [] as $basename) {
            if ($basename === '.' || $basename === '..') {
                continue;
            }

            $dir = $root . DIRECTORY_SEPARATOR . $basename;

            if (! is_dir($dir) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                continue;
            }

            try {
                $dirDate = Carbon::createFromFormat('Y-m-d', $basename)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if ($dirDate->gte($cutoff)) {
                continue;
            }

            $size = $this->directorySize($dir);

            if ($dryRun) {
                $this->line("  [dry-run] Would delete: {$basename} (" . $this->formatBytes($size) . ')');
            } else {
                $this->deleteDirectory($dir);
                $this->line("  Deleted: {$basename} (" . $this->formatBytes($size) . ')');
            }

            $deletedDirs++;
            $freedBytes += $size;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d date directories (%s).',
            $dryRun ? 'Would free' : 'Freed',
            $deletedDirs,
            $this->formatBytes($freedBytes)
        ));

        return self::SUCCESS;
    }

    private function directorySize(string $directory): int
    {
        $size = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function deleteDirectory(string $directory): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return round($value, 2) . ' ' . $units[$i];
    }
}
