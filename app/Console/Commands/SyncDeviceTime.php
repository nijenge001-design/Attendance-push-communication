<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Convenience wrapper around `device:sys set-time`.
 */
#[Signature('device:sync-time
    {--serial= : Target one device SN (or comma-separated list)}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--datetime= : Custom datetime (Y-m-d H:i:s). Defaults to now}
    {--force : Skip confirmation}')]
#[Description('Synchronize date & time on devices (alias of device:sys set-time)')]
class SyncDeviceTime extends Command
{
    use QueuesDeviceCommands;

    public function handle(DeviceCommandQueue $queue): int
    {
        try {
            $dateTime = $this->option('datetime')
                ? Carbon::parse((string) $this->option('datetime'))
                : now();
        } catch (Throwable $e) {
            $this->error('Invalid --datetime: '.$e->getMessage());

            return self::FAILURE;
        }

        $serials = $this->resolveTargetSerials(
            $this->option('serial'),
            $this->option('status'),
            (bool) $this->option('all')
        );

        if ($serials === []) {
            return self::FAILURE;
        }

        $body = CommandBuilder::setDateTime($dateTime);

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued time sync [{$dateTime->toDateTimeString()}] to {$total} device(s).");

        return self::SUCCESS;
    }
}
