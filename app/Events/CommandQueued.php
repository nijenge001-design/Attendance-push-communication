<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PendingCommand;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandQueued implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PendingCommand $command) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->command->device_serial),
            new PrivateChannel('commands'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'command.queued';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->command->id,
            'commandId'    => $this->command->command_id,
            'deviceSerial' => $this->command->device_serial,
            'commandText'  => $this->command->command_text,
            'isRecurring'  => $this->command->is_recurring,
            'scheduledAt'  => $this->command->scheduled_at?->toIso8601String(),
        ];
    }
}
