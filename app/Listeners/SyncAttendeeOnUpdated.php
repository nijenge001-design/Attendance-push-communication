<?php

namespace App\Listeners;

use App\Events\AttendeeUpdated;
use App\Jobs\SyncAttendeeToDevices;

/** Push updated attendee USERINFO to devices. */
class SyncAttendeeOnUpdated
{
    public function handle(AttendeeUpdated $event): void
    {
        SyncAttendeeToDevices::dispatch(
            $event->attendee->pin,
            $event->deviceSerials,
        );
    }
}
