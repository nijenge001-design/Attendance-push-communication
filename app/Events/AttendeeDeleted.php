<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an attendee is soft-deleted.
 * Carries pin + device serials (model may already be gone from relations).
 * Listener queues DELETE USERINFO to those devices.
 */
class AttendeeDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $deviceSerials
     */
    public function __construct(
        public string $pin,
        public array $deviceSerials = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('attendees')];
    }

    public function broadcastAs(): string
    {
        return 'attendee.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'pin'           => $this->pin,
            'deviceSerials' => $this->deviceSerials,
        ];
    }
}
