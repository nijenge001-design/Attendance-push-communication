<?php

namespace App\Console\Commands;

use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:broadcast {body? : Raw command body} {--status=approved : Target status (approved, pending, blocked, all)} {--reboot : Queue REBOOT} {--unlock : Queue AC_UNLOCK} {--info : Queue INFO} {--clear-log : Queue CLEAR LOG} {--clear-data : Queue CLEAR DATA} {--check : Re-read server configuration and re-upload data} {--log : Immediately check for new data and upload it} {--all-users : Query all users basic info} {--sync-time : Synchronize device clock with server time} {--force : Skip confirmation}')]
#[Description('Broadcast a command to multiple devices by status')]
class BroadcastDeviceCommand extends Command
{
    public function handle(DeviceCommandQueue $queue): int
    {
        $status = $this->option('status');
        if ($status === 'all') {
            $status = null;
        }

        $commands = [];

        if ($this->option('reboot')) {
            $commands[] = CommandBuilder::reboot();
        }
        if ($this->option('unlock')) {
            $commands[] = CommandBuilder::unlockDoor();
        }
        if ($this->option('info')) {
            $commands[] = CommandBuilder::info();
        }
        if ($this->option('clear-log')) {
            $commands[] = CommandBuilder::clearLog();
        }
        if ($this->option('clear-data')) {
            $commands[] = CommandBuilder::clearAllData();
        }
        if ($this->option('check')) {
            $commands[] = CommandBuilder::checkUpdate();
        }
        if ($this->option('log')) {
            $commands[] = CommandBuilder::checkAndTransmit();
        }
        if ($this->option('all-users')) {
            $commands[] = 'DATA QUERY USERINFO';
        }
        if ($this->option('sync-time')) {
            $commands[] = CommandBuilder::setDateTime();
        }

        if ($raw = $this->argument('body')) {
            $commands[] = (string) $raw;
        }

        if ($commands === []) {
            $this->error('No command specified. Use --reboot, --unlock, --info, --check, --log, --all-users, --sync-time, or a body.');

            return self::FAILURE;
        }

        $target = $status ? "devices with status [{$status}]" : 'ALL devices';
        $this->warn('About to queue ' . count($commands) . " command(s) to {$target}.");

        if (! $this->option('force') && ! $this->option('no-interaction')) {
            if (! $this->confirm('Do you want to continue?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $total = $queue->queueToDevicesByStatus($commands, $status);

        $this->info("Successfully queued {$total} command(s).");

        return self::SUCCESS;
    }
}
