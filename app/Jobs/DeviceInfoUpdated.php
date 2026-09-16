<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('devices')]
#[Tries(3)]
class DeviceInfoUpdated implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 60;
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Device $device)
    {
    }

    public function uniqueId(): string
    {
        return 'device-info-updated:' . $this->deviceIdentifier();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('device-info:' . $this->deviceIdentifier()))
                ->releaseAfter(30)
                ->expireAfter(30),
        ];
    }

    public function tags(): array
    {
        return ['device-info', 'device:' . $this->deviceIdentifier()];
    }

    public function handle(): void
    {
        $serial = $this->device->serial_number;

        if (blank($serial)) {
            Log::warning('DeviceInfoUpdated skipped: missing serial', [
                'device_id' => $this->device->getKey(),
            ]);
            return;
        }

        Log::info('Device info updated', [
            'serial' => $serial,
            'name' => $this->device->device_name,
            'users' => (int)$this->device->user_count,
            'fingers' => (int)$this->device->fp_count,
            'faces' => (int)$this->device->face_count,
            'ip' => $this->device->ip,
        ]);

        // TODO: sync inventory, update cache, publish state, webhooks
    }

    public function failed(Throwable $e): void
    {
        Log::error('DeviceInfoUpdated failed permanently', [
            'device_id' => $this->device->getKey(),
            'serial' => $this->device->serial_number,
            'error' => $e->getMessage(),
        ]);
    }

    private function deviceIdentifier(): string
    {
        return $this->device->serial_number ?? ('id-' . $this->device->getKey());
    }
}
