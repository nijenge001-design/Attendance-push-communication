<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceInfoUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Device $device) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('device.' . $this->device->serial_number),
            new PrivateChannel('devices'),
        ];

        if ($this->device->site_id) {
            $channels[] = new PrivateChannel('site.' . $this->device->site_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'device.info-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'             => $this->device->id,
            'serialNumber'   => $this->device->serial_number,
            'deviceName'     => $this->device->device_name,
            'userCount'      => $this->device->user_count,
            'fpCount'        => $this->device->fp_count,
            'faceCount'      => $this->device->face_count,
            'firmware'       => $this->device->firmware_version,
            'ip'             => $this->device->ip,
            'siteId'         => $this->device->site_id,
        ];
    }
}
