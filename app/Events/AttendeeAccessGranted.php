<?php

namespace App\Events;

use App\Models\Attendee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an attendee is granted access to a specific device.
 * Listener queues USERINFO push to that device.
 */
class AttendeeAccessGranted implements ShouldBroadcast
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
        return 'attendee.access-granted';
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
