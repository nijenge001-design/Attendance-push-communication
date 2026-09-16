<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PendingCommand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

#[Queue('devices')]
#[Tries(2)]
class ProcessRecurringCommands implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        PendingCommand::query()
            ->recurring()
            ->enabled()
            ->where('next_run_at', '<=', now())
            ->each(function (PendingCommand $cmd) {
                $cmd->update([
                    'executed' => false,
                    'sent_at' => null,
                    'available_at' => null,
                    'next_run_at' => $cmd->calculateNextRun(),
                ]);

                Log::info('Recurring command re-activated', [
                    'command_id' => $cmd->command_id,
                    'next_run' => $cmd->next_run_at,
                ]);
            });
    }
}
