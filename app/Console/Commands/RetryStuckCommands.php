<?php

namespace App\Console\Commands;

use App\Models\PendingCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:retry-stuck {--minutes=10 : Consider commands stuck after X minutes without ACK} {--delay=5 : Minutes to wait before next retry} {--dry-run : Show stuck commands only}')]
#[Description('Retry commands that were sent but never acknowledged by the device')]
class RetryStuckCommands extends Command
{
    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $delay = max(1, (int) $this->option('delay'));
        $dryRun = (bool) $this->option('dry-run');

        $stuck = PendingCommand::stuck($minutes)->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck commands found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['Device', 'CmdID', 'Command', 'Sent At'],
                $stuck->map(fn ($cmd) => [
                    $cmd->device_serial,
                    $cmd->command_id,
                    \Illuminate\Support\Str::limit((string) $cmd->command_text, 40),
                    $cmd->sent_at?->toDateTimeString() ?? '-',
                ])
            );
            $this->info('Stuck count: ' . $stuck->count());

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($stuck as $cmd) {
            if (method_exists($cmd, 'scheduleRetry')) {
                $cmd->scheduleRetry($delay);
            } else {
                $cmd->forceFill([
                    'sent_at' => null,
                    'next_run_at' => now()->addMinutes($delay),
                ])->save();
            }
            $count++;
        }

        $this->info("Scheduled retry for {$count} stuck command(s) (delay {$delay} min).");

        return self::SUCCESS;
    }
}
