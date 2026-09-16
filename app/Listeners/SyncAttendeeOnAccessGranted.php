<?php

namespace App\Listeners;

use App\Events\AttendeeAccessGranted;
use App\Jobs\SyncAttendeeToDevices;

/** Push attendee to a single device after access is granted. */
class SyncAttendeeOnAccessGranted
{
    public function handle(AttendeeAccessGranted $event): void
    {
        SyncAttendeeToDevices::dispatch(
            $event->attendee->pin,
            [$event->deviceSerial],
        );
    }
}
