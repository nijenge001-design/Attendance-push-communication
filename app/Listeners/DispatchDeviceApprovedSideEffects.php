<?php

namespace App\Listeners;

use App\Events\DeviceApproved;
use App\Jobs\DeviceApprovedSideEffects;

/** Bridges DeviceApproved → DeviceApprovedSideEffects job. */
class DispatchDeviceApprovedSideEffects
{
    public function handle(DeviceApproved $event): void
    {
        DeviceApprovedSideEffects::dispatch($event->device);
    }
}
