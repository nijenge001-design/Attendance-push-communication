<?php

namespace App\Listeners;

use App\Events\DeviceInfoUpdated;
use App\Jobs\DeviceInfoUpdated as DeviceInfoUpdatedJob;

/** Bridges DeviceInfoUpdated event → DeviceInfoUpdated job. */
class DispatchDeviceInfoUpdated
{
    public function handle(DeviceInfoUpdated $event): void
    {
        DeviceInfoUpdatedJob::dispatch($event->device);
    }
}
