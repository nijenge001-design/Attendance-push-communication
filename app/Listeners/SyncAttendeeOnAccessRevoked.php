<?php

namespace App\Listeners;

use App\Events\AttendeeAccessRevoked;
use App\Jobs\SyncAttendeeToDevices;

/** Send DELETE to a single device after access is revoked. */
class SyncAttendeeOnAccessRevoked
{
    public function handle(AttendeeAccessRevoked $event): void
    {
        SyncAttendeeToDevices::dispatch(
            $event->attendee->pin,
            [$event->deviceSerial],
            delete: true,
        );
    }
}
