<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Attendee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an attendee loses access to a specific device.
 * Listener queues DELETE USERINFO for that device.
 */
class AttendeeAccessRevoked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public string $deviceSerial,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('attendees'),
            new PrivateChannel('device.' . $this->deviceSerial),
        ];
    }

    public function broadcastAs(): string
    {
        return 'attendee.access-revoked';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->attendee->id,
            'pin'          => $this->attendee->pin,
            'name'         => $this->attendee->name,
            'deviceSerial' => $this->deviceSerial,
        ];
    }
}
