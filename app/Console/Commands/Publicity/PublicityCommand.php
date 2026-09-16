<?php

declare(strict_types=1);

namespace App\Console\Commands\Publicity;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:ad
    {action : update|delete|query}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--filename= : Picture filename}
    {--content= : Picture content for update}
    {--size= : Optional size}
    {--force : Skip confirmation}')]
#[Description('Publicity pictures: update, delete, or query advertising images on device')]
class PublicityCommand extends Command
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

        $filename = $this->option('filename');

        $body = match ($action) {
            'update' => $this->buildUpdate($filename),
            'delete' => $filename
                ? CommandBuilder::deletePublicityPicture($filename)
                : $this->abortWithError('--filename required for delete'),
            'query' => CommandBuilder::queryPublicityPictures(),
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
        $this->info("Queued {$total} publicity/{$action} command(s).");
        $this->line(\Illuminate\Support\Str::limit($body, 120));

        return self::SUCCESS;
    }

    private function buildUpdate(mixed $filename): ?string
    {
        if (! $filename || ! $this->option('content')) {
            return $this->abortWithError('--filename and --content are required for update');
        }

        return CommandBuilder::updatePublicityPicture(array_filter([
            'filename' => $filename,
            'content' => $this->option('content'),
            'size' => $this->option('size'),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function abortWithError(string $message): null
    {
        $this->error($message);
        return null;
    }
}
