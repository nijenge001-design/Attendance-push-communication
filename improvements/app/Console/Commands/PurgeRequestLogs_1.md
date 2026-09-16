```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PurgeRequestLogs extends Command
{
    protected $signature = 'logs:purge-requests
                            {--days= : Override retention days (default from config)}
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation}';

    protected $description = 'Purge old request log files (raw bodies + metadata) older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('logging.request.retention_days', 14));
        $days = max(1, $days);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $root = storage_path('logs/requests');

        if (! is_dir($root)) {
            $this->info('No request logs directory found. Nothing to purge.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->startOfDay();
        $this->info("Purging request logs older than {$cutoff->toDateString()} (retention: {$days} days)");

        if ($dryRun) {
            $this->warn('DRY-RUN mode – no files will be deleted.');
        }

        if (! $dryRun && ! $force && ! $this->confirm('Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deletedDirs = 0;
        $deletedFiles = 0;
        $freedBytes = 0;

        // Date directories are named Y-m-d
        $directories = File::directories($root);

        foreach ($directories as $dir) {
            $basename = basename($dir);

            // Only process directories that look like dates
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                continue;
            }

            try {
                $dirDate = \Carbon\Carbon::createFromFormat('Y-m-d', $basename)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if ($dirDate->gte($cutoff)) {
                continue;
            }

            // Calculate size before deletion
            $size = $this->directorySize($dir);

            if ($dryRun) {
                $this->line("  [dry-run] Would delete: {$basename} (" . $this->formatBytes($size) . ')');
            } else {
                File::deleteDirectory($dir);
                $this->line("  Deleted: {$basename} (" . $this->formatBytes($size) . ')');
            }

            $deletedDirs++;
            $freedBytes += $size;
        }

        // Also clean any loose files that might exist at the root (defensive)
        foreach (File::files($root) as $file) {
            if ($file->getMTime() < $cutoff->getTimestamp()) {
                $size = $file->getSize();
                if ($dryRun) {
                    $this->line('  [dry-run] Would delete file: ' . $file->getFilename());
                } else {
                    File::delete($file->getPathname());
                }
                $deletedFiles++;
                $freedBytes += $size;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d date directories and %d loose files (%s).',
            $dryRun ? 'Would free' : 'Freed',
            $deletedDirs,
            $deletedFiles,
            $this->formatBytes($freedBytes)
        ));

        return self::SUCCESS;
    }

    private function directorySize(string $directory): int
    {
        $size = 0;
        foreach (File::allFiles($directory) as $file) {
            $size += $file->getSize();
        }

        return $size;
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
```
