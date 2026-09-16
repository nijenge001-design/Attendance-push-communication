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

#[Queue('notifications')]
#[Tries(3)]
#[Backoff(30, 60, 120)]
class NotifyDeviceOffline implements ShouldQueue, ShouldBeUnique
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
            'notify-device-offline:%s:%s',
            $this->device->serial_number ?? $this->device->getKey(),
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
        return [
            'notification',
            'device-offline',
            'device:' . ($this->device->serial_number ?? $this->device->getKey()),
        ];
    }

    public function handle(): void
    {
        // Skip if device came back online recently
        if ($this->device->last_seen_at?->gt(now()->subMinutes(2))) {
            Log::info('NotifyDeviceOffline skipped: device recently seen', [
                'serial' => $this->device->serial_number,
            ]);
            return;
        }

        Log::warning('Sending device offline notification', [
            'serial' => $this->device->serial_number,
            'name' => $this->device->device_name,
            'last_seen' => $this->device->last_seen_at?->toDateTimeString(),
        ]);

        // TODO: Replace with your actual notification
        // Notification::route('mail', 'admin@example.com')
        //     ->notify(new \App\Notifications\DeviceOfflineNotification($this->device));
        //
        // Or use Slack / SMS / Discord / custom channels
    }

    public function failed(Throwable $e): void
    {
        Log::error('NotifyDeviceOffline failed permanently', [
            'serial' => $this->device->serial_number,
            'error' => $e->getMessage(),
        ]);
    }
}
