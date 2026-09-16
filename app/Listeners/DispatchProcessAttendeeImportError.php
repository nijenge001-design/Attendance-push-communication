<?php

namespace App\Listeners;


use App\Events\AttendeeImportErrorCreated;
use App\Jobs\ProcessAttendeeImportError;

/** Bridges AttendeeImportErrorCreated → ProcessAttendeeImportError job. */
class DispatchProcessAttendeeImportError
{
    public function handle(AttendeeImportErrorCreated $event): void
    {
        ProcessAttendeeImportError::dispatch($event->error);
    }
}
