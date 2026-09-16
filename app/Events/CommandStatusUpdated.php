<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PendingCommand;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandStatusUpdated implements ShouldBroadcastNow
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
        return 'command.status';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->command->id,
            'commandId'    => $this->command->command_id,
            'deviceSerial' => $this->command->device_serial,
            'executed'     => $this->command->executed,
            'returnCode'   => $this->command->return_code,
            'retryCount'   => $this->command->retry_count,
            'result'       => $this->command->result,
            'executedAt'   => $this->command->executed_at?->toIso8601String(),
            'sentAt'       => $this->command->sent_at?->toIso8601String(),
        ];
    }
}
