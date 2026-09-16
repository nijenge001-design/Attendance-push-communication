<?php

namespace App\Observers;

use App\Models\Device;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;

class DeviceObserver
{
    public function created(Device $device): void
    {
        //
    }

    public function updated(Device $device): void
    {
        if ($device->wasChanged('status') && $device->isApproved()) {
            app(DeviceCommandQueue::class)->queue(
                $device->serial_number,
                CommandBuilder::checkUpdate() // "CHECK"
            );

//             Optional: also sync time + refresh INFO
            app(DeviceCommandQueue::class)->queue(
                $device->serial_number,
                CommandBuilder::setDateTime() // "SET_DATETIME"
            );
            app(DeviceCommandQueue::class)->queue(
                $device->serial_number,
                CommandBuilder::info() // "INFO"
            );
        }
    }

    public function deleted(Device $device): void
    {
        //
    }

    public function restored(Device $device): void
    {
        //
    }

    public function forceDeleted(Device $device): void
    {
        //
    }
}
