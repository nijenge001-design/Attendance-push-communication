<?php

declare(strict_types=1);

namespace App\Console\Commands\Files;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:file
    {action : put|get|delete}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--path= : Remote file path on the device}
    {--url= : URL of the file (required for put)}
    {--put-action= : Optional PutFile Action parameter (e.g. SyncData)}
    {--force : Skip confirmation}')]
#[Description('File operations: put, get, or delete a file on the device')]
class FileCommand extends Command
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

        $path = $this->option('path');

        $body = match ($action) {
            'put'    => $this->buildPut($path),
            'get'    => $this->buildGet($path),
            'delete' => $this->buildDelete($path),
            default  => $this->abortWithError('action must be put, get, or delete'),
        };

        if ($body === null) {
            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->option('force') && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);

        $this->info("Queued {$total} file/{$action} command(s).");
        $this->line(\Illuminate\Support\Str::limit($body, 140));

        return self::SUCCESS;
    }

    private function buildPut(?string $path): ?string
    {
        $url = $this->option('url');

        if (! $path || ! $url) {
            return $this->abortWithError('--path and --url are required for put');
        }

        return CommandBuilder::putFile(
            url: $url,
            filePath: $path,
            action: $this->option('put-action')
        );
    }

    private function buildGet(?string $path): ?string
    {
        if (! $path) {
            return $this->abortWithError('--path is required for get');
        }

        return CommandBuilder::getFile($path);
    }

    private function buildDelete(?string $path): ?string
    {
        if (! $path) {
            return $this->abortWithError('--path is required for delete');
        }

        return CommandBuilder::deleteFile($path);
    }
}
