```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PendingCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:process-scheduled')]
#[Description('Prepare due recurring commands so getrequest can pick them up again')]
class ProcessScheduledCommands extends Command
{
    public function handle(): int
    {
        // Recurring rows that are due again: reset send/execute flags for next delivery
        $due = PendingCommand::query()
            ->where('is_recurring', true)
            ->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $count = 0;

        foreach ($due as $command) {
            $command->forceFill([
                'executed' => false,
                'sent_at' => null,
                'executed_at' => null,
                // keep next_run_at; device execution path should advance it after ACK
            ])->save();

            $count++;
        }

        if ($count > 0) {
            $this->info("Prepared {$count} recurring command(s) for delivery.");
        } else {
            $this->line('No due recurring commands.');
        }

        return self::SUCCESS;
    }
}
