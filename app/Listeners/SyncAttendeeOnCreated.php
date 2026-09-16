<?php

namespace App\Listeners;

use App\Events\AttendeeCreated;
use App\Jobs\DispatchMailJob;
use App\Jobs\SyncAttendeeToDevices;
use Throwable;

/** Push new attendee USERINFO to the listed devices. */
class SyncAttendeeOnCreated
{
    public function handle(AttendeeCreated $event): void
    {
        if ($event->deviceSerials === []) {
            return;
        }

        try {
            DispatchMailJob::push(new SyncAttendeeToDevices(
                $event->attendee->pin,
                $event->deviceSerials,
            ));
        } catch (Throwable $e) {
            // Never roll back Attendee::create because the device push failed.
            report($e);
        }
    }
}
