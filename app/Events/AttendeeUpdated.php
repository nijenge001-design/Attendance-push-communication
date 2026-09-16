<?php

namespace App\Events;

use App\Models\Attendee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendeeUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param list<string> $deviceSerials
     */
    public function __construct(
        public Attendee $attendee,
        public array $deviceSerials = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('attendees')];
    }

    public function broadcastAs(): string
    {
        return 'attendee.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'   => $this->attendee->id,
            'pin'  => $this->attendee->pin,
            'name' => $this->attendee->name,
        ];
    }
}
