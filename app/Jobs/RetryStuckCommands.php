<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PendingCommand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

#[Queue('devices')]
#[Tries(2)]
#[Backoff(60)]
class RetryStuckCommands implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        PendingCommand::query()
            ->stuck(minutes: 10)
            ->limit(100)
            ->get()
            ->each(function (PendingCommand $cmd) {
                $cmd->scheduleRetry(delayMinutes: 5);

                Log::info('Re-queued stuck command', [
                    'command_id' => $cmd->command_id,
                    'device' => $cmd->device_serial,
                ]);
            });
    }
}
