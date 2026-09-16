<?php

declare(strict_types=1);

namespace App\Console\Commands\System;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('device:sys
    {action : info|check|reboot|unlock|set-time|set-option|clear-data|shell}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--datetime= : For set-time (Y-m-d H:i:s), default now}
    {--option=* : key=value pairs for set-option (repeatable)}
    {--cmd= : Shell command string for shell action}
    {--force : Skip confirmation}')]
#[Description('System commands: INFO, CHECK, REBOOT, unlock, time, options, clear data, shell')]
class SystemCommand extends Command
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

        try {
            $body = match ($action) {
                'info' => CommandBuilder::info(),
                'check' => CommandBuilder::checkUpdate(),
                'reboot' => CommandBuilder::reboot(),
                'unlock' => CommandBuilder::unlockDoor(),
                'set-time' => CommandBuilder::setDateTime(
                    $this->option('datetime')
                        ? Carbon::parse((string) $this->option('datetime'))
                        : now()
                ),
                'set-option' => $this->buildOptions(),
                'clear-data' => CommandBuilder::clearAllData(),
                'shell' => $this->option('cmd')
                    ? CommandBuilder::shell((string) $this->option('cmd'))
                    : null,
                default => null,
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($body === null) {
            $this->error('Invalid action or missing required options (e.g. --cmd for shell, --option for set-option).');

            return self::FAILURE;
        }

        if (in_array($action, ['reboot', 'clear-data'], true) && ! $this->option('force')) {
            if (! $this->confirm("Destructive action [{$action}] — continue?")) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        } elseif (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} sys/{$action} command(s).");
        $this->line($body);

        return self::SUCCESS;
    }

    private function buildOptions(): ?string
    {
        $pairs = $this->option('option') ?? [];
        if ($pairs === []) {
            return null;
        }

        $options = [];
        foreach ($pairs as $pair) {
            if (! str_contains($pair, '=')) {
                $this->error("Invalid --option format (use key=value): {$pair}");

                return null;
            }
            [$k, $v] = explode('=', $pair, 2);
            $options[trim($k)] = trim($v);
        }

        return CommandBuilder::setOptions($options);
    }
}
