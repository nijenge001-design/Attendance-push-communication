<?php

namespace App\Listeners;


use App\Events\AttendancePunched;
use App\Jobs\ProcessAttendanceLog;

/**
 * Bridges AttendancePunched → ProcessAttendanceLog job.
 *
 * Jobs must not be registered directly in $listen when their constructor
 * expects a model; the dispatcher cannot resolve that from the container.
 */
class DispatchProcessAttendanceLog
{
    public function handle(AttendancePunched $event): void
    {
        ProcessAttendanceLog::dispatch($event->log);
    }
}
