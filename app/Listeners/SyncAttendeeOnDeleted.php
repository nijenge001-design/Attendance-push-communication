<?php

namespace App\Listeners;

use App\Events\AttendeeDeleted;
use App\Jobs\SyncAttendeeToDevices;

/** Send DELETE USERINFO to devices when an attendee is removed. */
class SyncAttendeeOnDeleted
{
    public function handle(AttendeeDeleted $event): void
    {
        if ($event->deviceSerials === []) {
            return;
        }

        SyncAttendeeToDevices::dispatch(
            $event->pin,
            $event->deviceSerials,
            delete: true,
        );
    }
}
