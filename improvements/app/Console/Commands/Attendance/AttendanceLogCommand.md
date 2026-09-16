```php
<?php

declare(strict_types=1);

namespace App\Console\Commands\Attendance;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:attlog
    {action : query|upload|clear}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--start= : Start time (Y-m-d H:i:s) for query}
    {--end= : End time (Y-m-d H:i:s) for query}
    {--force : Skip confirmation}')]
#[Description('Attendance logs: query range, force upload (LOG), or clear device log')]
class AttendanceLogCommand extends Command
{
    use QueuesDeviceCommands;

    public function handle(DeviceCommandQueue $queue): int
    {
        $action = strtolower((string) $this->argument('action'));
        $serials = $this->resolveTargetSerials(
            $this->option('serial'),
            $this->option('status'),
            (bool) $this->option('all')
        );

        if ($serials === []) {
            return self::FAILURE;
        }

        $body = match ($action) {
            'query' => CommandBuilder::queryAttLog(
                $this->option('start') ?: null,
                $this->option('end') ?: null,
            ),
            'upload' => CommandBuilder::checkAndTransmit(),
            'clear' => CommandBuilder::clearLog(),
            default => null,
        };

        if ($body === null) {
            $this->error('action must be query, upload, or clear.');

            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} attlog/{$action} command(s).");
        $this->line($body);

        return self::SUCCESS;
    }
}
