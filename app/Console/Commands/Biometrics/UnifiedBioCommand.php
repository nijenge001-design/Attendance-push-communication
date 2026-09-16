<?php

declare(strict_types=1);

namespace App\Console\Commands\Biometrics;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:bio
    {action : update|delete|query}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--pin= : User PIN}
    {--type= : Bio type (protocol Type field)}
    {--index= : Bio index}
    {--template= : Template / No payload}
    {--format= : Format code}
    {--major= : Major version}
    {--minor= : Minor version}
    {--force : Skip confirmation}')]
#[Description('Unified biometrics (BIODATA): update, delete, or query')]
class UnifiedBioCommand extends Command
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

        $pin = $this->option('pin');
        $type = $this->option('type') !== null && $this->option('type') !== ''
            ? (int) $this->option('type')
            : null;
        $index = $this->option('index') !== null && $this->option('index') !== ''
            ? (int) $this->option('index')
            : null;

        $body = match ($action) {
            'update' => $this->buildUpdate($pin, $type, $index),
            'delete' => $pin
                ? CommandBuilder::deleteBioData($pin, $type, $index)
                : $this->abortWithError('--pin required for delete'),
            'query' => $pin
                ? CommandBuilder::queryBioData($pin, $type)
                : $this->abortWithError('--pin required for query'),
            default => $this->abortWithError('action must be update, delete, or query'),
        };

        if ($body === null) {
            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} bio/{$action} command(s).");
        $this->line($body);

        return self::SUCCESS;
    }

    private function buildUpdate(mixed $pin, ?int $type, ?int $index): ?string
    {
        if (! $pin || $type === null || ! $this->option('template')) {
            return $this->abortWithError('--pin, --type and --template are required for update');
        }

        return CommandBuilder::updateBioData(array_filter([
            'pin' => $pin,
            'type' => $type,
            'index' => $index ?? 0,
            'template' => $this->option('template'),
            'format' => $this->option('format'),
            'major' => $this->option('major'),
            'minor' => $this->option('minor'),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function abortWithError(string $message): null
    {
        $this->error($message);

        return null;
    }
}
