<?php

declare(strict_types=1);

namespace App\Console\Commands\User;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:user
    {action : update|delete|query}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices (ignore --status)}
    {--pin= : User PIN (required for update/delete; optional for query)}
    {--name= : User name}
    {--privilege= : Privilege level (Pri)}
    {--password= : Device password}
    {--card= : Card number}
    {--group= : Group id}
    {--tz= : Time zone string}
    {--verify= : Verify mode}
    {--force : Skip confirmation for multi-device}')]
#[Description('User management: update, delete, or query USERINFO on devices')]
class UserCommand extends Command
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
            'update' => $this->buildUpdate(),
            'delete' => $this->buildDelete(),
            'query' => CommandBuilder::queryUserInfo($this->option('pin') ?: null),
            default => null,
        };

        if ($body === null) {
            $this->error('action must be update, delete, or query.');

            return self::FAILURE;
        }

        if (is_int($body)) {
            return $body; // build* returned failure code
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} user/{$action} command(s).");
        $this->line($body);

        return self::SUCCESS;
    }

    private function buildUpdate(): string|int
    {
        $pin = $this->option('pin');
        if (! $pin) {
            $this->error('--pin is required for update.');

            return self::FAILURE;
        }

        $data = array_filter([
            'pin' => $pin,
            'name' => $this->option('name'),
            'privilege' => $this->option('privilege'),
            'password' => $this->option('password'),
            'card' => $this->option('card'),
            'group' => $this->option('group'),
            'time_zone' => $this->option('tz'),
            'verify' => $this->option('verify'),
        ], fn ($v) => $v !== null && $v !== '');

        return CommandBuilder::updateUser($data);
    }

    private function buildDelete(): string|int
    {
        $pin = $this->option('pin');
        if (! $pin) {
            $this->error('--pin is required for delete.');

            return self::FAILURE;
        }

        return CommandBuilder::deleteUser($pin);
    }
}
