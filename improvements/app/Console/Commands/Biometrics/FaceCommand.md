```php
<?php

declare(strict_types=1);

namespace App\Console\Commands\Biometrics;

use App\Console\Commands\Concerns\QueuesDeviceCommands;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:face
    {action : update|delete|query}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--pin= : User PIN}
    {--fid=0 : Face index}
    {--template= : Face template (update)}
    {--size= : Template size}
    {--valid=1 : Valid flag}
    {--force : Skip confirmation}')]
#[Description('Face biometrics: update, delete, or query')]
class FaceCommand extends Command
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
        $fid = $this->option('fid') !== null ? (int) $this->option('fid') : null;

        $body = match ($action) {
            'update' => $this->buildUpdate($pin, $fid),
            'delete' => $pin
                ? CommandBuilder::deleteFace($pin, $fid)
                : $this->fail('--pin required for delete'),
            'query' => $pin
                ? CommandBuilder::queryFace($pin)
                : $this->fail('--pin required for query'),
            default => $this->fail('action must be update, delete, or query'),
        };

        if ($body === null) {
            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} face/{$action} command(s).");
        $this->line($body);

        return self::SUCCESS;
    }

    private function buildUpdate(mixed $pin, ?int $fid): ?string
    {
        if (! $pin || ! $this->option('template')) {
            return $this->fail('--pin and --template are required for update');
        }

        return CommandBuilder::updateFace(array_filter([
            'pin' => $pin,
            'fid' => $fid ?? 0,
            'template' => $this->option('template'),
            'size' => $this->option('size'),
            'valid' => $this->option('valid'),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function fail(string $message): null
    {
        $this->error($message);

        return null;
    }
}
