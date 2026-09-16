<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\AttendancePunched;
use App\Models\AttendanceLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Queue('attendance')]
#[Tries(3)]
#[Timeout(120)]
#[Backoff(10, 30)]
class ProcessBulkAttendanceChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param list<string> $logIds
     */
    public function __construct(
        public array $logIds,
        public bool  $broadcast = false,
    )
    {
    }

    public function handle(): void
    {
        $logs = AttendanceLog::query()->whereIn('id', $this->logIds)->get();

        foreach ($logs as $log) {
            ProcessAttendanceLog::dispatch($log);

            if ($this->broadcast) {
                event(new AttendancePunched($log));
            }
        }
    }
}
