<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs after a device is approved:
 *  1. Request fresh device info (INFO)
 *  2. Re-sync all active attendees to that device
 */
#[Queue('devices')]
#[Tries(3)]
#[Backoff(15, 45, 90)]
#[Timeout(120)]
class DeviceApprovedSideEffects implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Device $device)
    {
    }

    public function tags(): array
    {
        return [
            'device-approved',
            'device:' . ($this->device->serial_number ?? $this->device->getKey()),
        ];
    }

    public function handle(): void
    {
        $serial = $this->device->serial_number;

        if (blank($serial)) {
            Log::warning('DeviceApprovedSideEffects skipped: missing serial', [
                'device_id' => $this->device->getKey(),
            ]);
            return;
        }

        // Ask the device to report capabilities / counts.
        PushDeviceInfoRequest::dispatch($this->device);

        // Push every active attendee already linked to this device.
        $pins = $this->device->attendees()
            ->wherePivot('active', true)
            ->pluck('pin');

        foreach ($pins as $pin) {
            SyncAttendeeToDevices::dispatch((string)$pin, [$serial]);
        }

        Log::info('DeviceApprovedSideEffects completed', [
            'serial' => $serial,
            'attendees_synced' => $pins->count(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('DeviceApprovedSideEffects failed permanently', [
            'serial' => $this->device->serial_number ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
