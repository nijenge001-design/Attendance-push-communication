<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\Attendee;
use App\Models\BioTemplate;
use App\Models\Device;
use App\Models\PendingCommand;
use App\Models\Photo;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

#[Queue('maintenance')]
#[Tries(2)]
#[Timeout(300)]
class PruneSoftDeletedRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * How many days to keep soft-deleted records.
     */
    public int $days = 90;

    public function handle(): void
    {
        $cutoff = now()->subDays($this->days);

        $counts = [
            'attendance_logs' => AttendanceLog::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'bio_templates' => BioTemplate::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'photos' => Photo::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'pending_commands' => PendingCommand::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'attendees' => Attendee::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'devices' => Device::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'sites' => Site::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
        ];

        Log::info('PruneSoftDeletedRecords finished', [
            'cutoff' => $cutoff->toDateTimeString(),
            'counts' => $counts,
        ]);
    }
}
