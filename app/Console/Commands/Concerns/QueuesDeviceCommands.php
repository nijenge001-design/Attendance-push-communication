<?php

namespace App\Console\Commands\Concerns;

use App\Models\Device;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Command;

/**
 * Shared helpers for category artisan commands that queue protocol bodies.
 *
 * @mixin Command
 */
trait QueuesDeviceCommands
{
    /**
     * @return list<string>
     */
    protected function resolveTargetSerials(?string $serial, ?string $status, bool $all): array
    {
        if ($serial) {
            $serial = trim($serial);
            $exists = Device::where('serial_number', $serial)->exists();
            if (! $exists) {
                $this->error("Device {$serial} not found.");

                return [];
            }

            return [$serial];
        }

        if ($all) {
            $status = null;
        }

        $query = Device::query();
        if ($status) {
            $query->where('status', $status);
        } else {
            $query->where('status', Device::STATUS_APPROVED);
        }

        $serials = $query->pluck('serial_number')->all();

        if ($serials === []) {
            $this->warn('No matching devices.');
        }

        return $serials;
    }

    /**
     * @param  list<string>  $serials
     * @param  list<string>|string  $commands
     */
    protected function queueToSerials(DeviceCommandQueue $queue, array $serials, array|string $commands): int
    {
        $commands = is_array($commands) ? $commands : [$commands];
        $total = 0;

        foreach ($serials as $sn) {
            foreach ($commands as $body) {
                $queue->queue($sn, $body);
                $total++;
            }
        }

        return $total;
    }

    protected function confirmBroadcast(int $deviceCount, int $commandCount): bool
    {
        if ($this->option('force') || $this->option('no-interaction')) {
            return true;
        }

        return $this->confirm(
            "Queue {$commandCount} command(s) to {$deviceCount} device(s)?"
        );
    }
}
