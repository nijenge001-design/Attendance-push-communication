<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceOffline implements ShouldBroadcastNow
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
        return 'device.offline';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->device->id,
            'serialNumber' => $this->device->serial_number,
            'deviceName'   => $this->device->device_name,
            'ip'           => $this->device->ip,
            'isOnline'     => false,
            'lastSeenAt'   => $this->device->last_seen_at?->toIso8601String(),
            'siteId'       => $this->device->site_id,
        ];
    }
}
