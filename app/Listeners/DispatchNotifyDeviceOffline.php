<?php

namespace App\Listeners;

use App\Events\DeviceOffline;
use App\Jobs\NotifyDeviceOffline;

/** Bridges DeviceOffline → NotifyDeviceOffline job. */
class DispatchNotifyDeviceOffline
{
    public function handle(DeviceOffline $event): void
    {
        NotifyDeviceOffline::dispatch($event->device);
    }
}
