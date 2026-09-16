```php
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
    {--path= : Remote file path on device}
    {--content= : File content for put (raw/base64 as required by firmware)}
    {--size= : Optional size for put}
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
            'put' => $this->buildPut($path),
            'get' => $path ? CommandBuilder::getFile($path) : $this->fail('--path required for get'),
            'delete' => $path ? CommandBuilder::deleteFile($path) : $this->fail('--path required for delete'),
            default => $this->fail('action must be put, get, or delete'),
        };

        if ($body === null) {
            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} file/{$action} command(s).");
        $this->line(\Illuminate\Support\Str::limit($body, 120));

        return self::SUCCESS;
    }

    private function buildPut(mixed $path): ?string
    {
        if (! $path || ! $this->option('content')) {
            return $this->fail('--path and --content are required for put');
        }

        return CommandBuilder::putFile(array_filter([
            'path' => $path,
            'content' => $this->option('content'),
            'size' => $this->option('size'),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function fail(string $message): null
    {
        $this->error($message);

        return null;
    }
}
