```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:command {serial : Device serial number} {body? : Raw command body} {--reboot : Queue REBOOT} {--unlock : Queue AC_UNLOCK} {--info : Queue INFO} {--clear-log : Queue CLEAR LOG} {--check : Queue CHECK} {--log : Queue LOG} {--sync-time : Sync device time} {--user= : PIN for user commands} {--name= : Name when updating user}')]
#[Description('Queue a command for a single device')]
class QueueDeviceCommand extends Command
{
    public function handle(DeviceCommandQueue $queue): int
    {
        $serial = trim((string) $this->argument('serial'));

        $device = Device::where('serial_number', $serial)->first();

        if (! $device) {
            $this->error("Device {$serial} not found.");

            return self::FAILURE;
        }

        if (! $device->isApproved()) {
            $this->warn("Device {$serial} status is [{$device->status}] — command will queue but device may not poll until approved.");
        }

        if ($this->option('reboot')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::reboot());
        }

        if ($this->option('unlock')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::unlockDoor());
        }

        if ($this->option('info')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::info());
        }

        if ($this->option('clear-log')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::clearLog());
        }

        if ($this->option('check')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::checkUpdate());
        }

        if ($this->option('log')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::checkAndTransmit());
        }

        if ($this->option('sync-time')) {
            return $this->queueAndReport($queue, $serial, CommandBuilder::setDateTime());
        }

        if ($pin = $this->option('user')) {
            $data = ['pin' => $pin];
            if ($name = $this->option('name')) {
                $data['name'] = $name;
            }

            return $this->queueAndReport($queue, $serial, CommandBuilder::updateUser($data));
        }

        $raw = $this->argument('body');
        if (! $raw) {
            $this->error('Provide a command body or use an option (--reboot, --info, --check, --sync-time, …).');

            return self::FAILURE;
        }

        return $this->queueAndReport($queue, $serial, (string) $raw);
    }

    private function queueAndReport(DeviceCommandQueue $queue, string $serial, string $command): int
    {
        $pending = $queue->queue($serial, $command);

        $this->info('Command queued successfully.');
        $this->line("CmdID   : {$pending->command_id}");
        $this->line('Command : ' . $pending->command_text);

        return self::SUCCESS;
    }
}
