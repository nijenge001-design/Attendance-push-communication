<?php

namespace App\Console\Commands;

use App\Models\PendingCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:cleanup-commands {--days=30 : Delete executed non-recurring commands older than X days} {--dry-run : Count only, do not delete}')]
#[Description('Clean up old executed device commands')]
class CleanupDeviceCommands extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $query = PendingCommand::query()
            ->where('executed', true)
            ->where(function ($q) {
                $q->where('is_recurring', false)->orWhereNull('is_recurring');
            })
            ->where('executed_at', '<', now()->subDays($days));

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No old executed commands to clean.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would delete {$count} executed command(s) older than {$days} day(s).");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} old executed command(s).");

        return self::SUCCESS;
    }
}
