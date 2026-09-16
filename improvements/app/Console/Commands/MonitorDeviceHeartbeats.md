```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:heartbeat {--threshold=5 : Minutes to consider a device offline} {--offline-only : Show only offline devices} {--watch : Continuously refresh the status} {--interval=5 : Refresh interval in seconds when using --watch}')]
#[Description('Monitor device heartbeats (online/offline status)')]
class MonitorDeviceHeartbeats extends Command
{
    public function handle(): int
    {
        if ($this->option('watch')) {
            return $this->watch();
        }

        return $this->renderOnce();
    }

    private function watch(): int
    {
        $interval = max(2, (int) $this->option('interval'));

        $this->info("Watching device heartbeats every {$interval}s (Ctrl+C to stop)...");
        $this->newLine();

        while (true) {
            if (PHP_OS_FAMILY === 'Windows') {
                system('cls');
            } else {
                system('clear');
            }

            $this->line('<fg=cyan>Device Heartbeat Monitor</>  ' . now()->toDateTimeString());
            $this->line(str_repeat('─', 70));

            $this->renderOnce(false);

            sleep($interval);
        }
    }

    private function renderOnce(bool $showHeader = true): int
    {
        $threshold = max(1, (int) $this->option('threshold'));

        $query = Device::query()->orderByDesc('last_seen_at');

        if ($this->option('offline-only')) {
            if (method_exists(Device::class, 'scopeOffline')) {
                $query->offline($threshold);
            } else {
                $query->where(function ($q) use ($threshold) {
                    $q->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', now()->subMinutes($threshold));
                });
            }
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->info('No devices found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Serial', 'Name', 'Status', 'Online', 'Last Seen', 'Source', 'IP', 'Negotiated'],
            $devices->map(function (Device $d) use ($threshold) {
                $online = $d->isOnline($threshold);
                $caps = $d->capabilities ?? [];

                return [
                    $d->serial_number,
                    $d->device_name ?? '-',
                    $d->status,
                    $online ? '<fg=green>YES</>' : '<fg=red>NO</>',
                    $d->last_seen_at?->diffForHumans() ?? 'Never',
                    $d->last_heartbeat_source ?? '-',
                    $d->ip ?? '-',
                    $caps['negotiated'] ?? '-',
                ];
            })
        );

        $onlineCount = $devices->filter(fn (Device $d) => $d->isOnline($threshold))->count();
        $offlineCount = $devices->count() - $onlineCount;

        $this->newLine();
        $this->line("Online: <fg=green>{$onlineCount}</>  |  Offline: <fg=red>{$offlineCount}</>  |  Threshold: {$threshold} min");

        return self::SUCCESS;
    }
}
