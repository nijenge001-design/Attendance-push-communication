<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('devices')]
#[Tries(3)]
#[Backoff(30, 60, 120)]
class DeviceOfflineAlert implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Device $device)
    {
    }

    public function uniqueId(): string
    {
        return sprintf(
            'device-offline:%s:%s',
            $this->deviceIdentifier(),
            $this->device->last_seen_at?->timestamp ?? 'unknown'
        );
    }

    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(5, 5))->backoff(600),
        ];
    }

    public function tags(): array
    {
        return ['device-offline', 'device:' . $this->deviceIdentifier()];
    }

    public function handle(): void
    {
        $serial = $this->device->serial_number;

        if (blank($serial)) {
            Log::warning('DeviceOfflineAlert skipped: missing serial');
            return;
        }

        if ($this->device->last_seen_at?->gt(now()->subMinutes(2))) {
            Log::info('DeviceOfflineAlert skipped: device recently seen', [
                'serial' => $serial,
            ]);
            return;
        }

        Log::warning('Device offline alert', [
            'serial' => $serial,
            'name' => $this->device->device_name,
            'last_seen' => $this->device->last_seen_at?->toDateTimeString(),
            'ip' => $this->device->ip,
        ]);

        // TODO: send email / SMS / Slack / webhook
    }

    public function failed(Throwable $e): void
    {
        Log::error('DeviceOfflineAlert failed permanently', [
            'serial' => $this->device->serial_number,
            'error' => $e->getMessage(),
        ]);
    }

    private function deviceIdentifier(): string
    {
        return $this->device->serial_number ?? ('id-' . $this->device->getKey());
    }
}
