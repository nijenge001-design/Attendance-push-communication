<?php

namespace App\Events;

use App\Models\AttendanceLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendancePunched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AttendanceLog $log) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('device.' . $this->log->device_serial),
            new PrivateChannel('attendance'),
        ];

        if ($siteId = $this->log->device?->site_id) {
            $channels[] = new PrivateChannel('site.' . $siteId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'attendance.punched';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->log->id,
            'pin'          => $this->log->pin,
            'deviceSerial' => $this->log->device_serial,
            'timestamp'    => $this->log->timestamp?->toIso8601String(),
            'status'       => $this->log->status,
            'statusLabel'  => $this->log->statusLabel(),
            'verifyMode'   => $this->log->verify_mode,
            'maskFlag'     => $this->log->mask_flag,
            'temperature'  => $this->log->temperature,
        ];
    }
}
