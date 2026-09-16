<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AttendanceLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

#[Queue('attendance')]
#[Tries(3)]
class GenerateDailyAttendanceSummary implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 86400;

    public function __construct(
        public Carbon  $date,
        public ?string $siteId = null,
    )
    {
    }

    public function uniqueId(): string
    {
        return 'daily-summary:' . $this->date->toDateString() . ':' . ($this->siteId ?? 'all');
    }

    public function handle(): void
    {
        $logs = AttendanceLog::query()
            ->whereDate('timestamp', $this->date)
            ->when($this->siteId, fn($q) => $q->whereHas('device', fn($d) => $d->where('site_id', $this->siteId))
            )
            ->get()
            ->groupBy('pin');

        foreach ($logs as $pin => $punches) {
            // TODO: calculate first check-in, last check-out, late, OT, total hours
            // Persist to a daily_summaries table or cache

            Log::info('Daily summary calculated', [
                'pin' => $pin,
                'date' => $this->date->toDateString(),
                'punches' => $punches->count(),
            ]);
        }
    }
}
