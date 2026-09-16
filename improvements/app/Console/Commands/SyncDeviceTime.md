```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('device:sync-time {--status=approved : Target devices status (approved|pending|blocked|all)} {--datetime= : Custom datetime (Y-m-d H:i:s). Defaults to now} {--force : Skip confirmation}')]
#[Description('Synchronize date & time on devices')]
class SyncDeviceTime extends Command
{
    public function handle(DeviceCommandQueue $queue): int
    {
        $status = $this->option('status');
        if ($status === 'all') {
            $status = null;
        }

        try {
            $dateTime = $this->option('datetime')
                ? Carbon::parse((string) $this->option('datetime'))
                : now();
        } catch (Throwable $e) {
            $this->error('Invalid --datetime: ' . $e->getMessage());

            return self::FAILURE;
        }

        $command = CommandBuilder::setDateTime($dateTime);

        $target = $status ? "devices with status [{$status}]" : 'ALL devices';
        $this->warn("About to set time to [{$dateTime->toDateTimeString()}] on {$target}.");

        if (! $this->option('force') && ! $this->option('no-interaction')) {
            if (! $this->confirm('Do you want to continue?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $total = $queue->queueToDevicesByStatus($command, $status);

        $this->info("Queued time sync to {$total} device(s).");

        return self::SUCCESS;
    }
}
