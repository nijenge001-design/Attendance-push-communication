<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AttendeeImportError;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendeeImportErrorCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AttendeeImportError $error) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->error->device_serial),
            new PrivateChannel('import-errors'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import-error.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->error->id,
            'deviceSerial' => $this->error->device_serial,
            'pin'          => $this->error->pin,
            'errorCode'    => $this->error->error_code,
            'errorMessage' => $this->error->error_message,
            'status'       => $this->error->status,
        ];
    }
}
