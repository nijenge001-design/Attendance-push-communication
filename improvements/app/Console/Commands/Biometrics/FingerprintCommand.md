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

#[Signature('device:fp
    {action : update|delete|enroll|query}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--pin= : User PIN}
    {--fid=0 : Finger ID (0-9)}
    {--template= : Template payload (update)}
    {--size= : Template size (optional)}
    {--valid=1 : Valid flag}
    {--retry=0 : Enroll retry count}
    {--force : Skip confirmation}')]
#[Description('Fingerprint biometrics: update, delete, enroll, or query')]
class FingerprintCommand extends Command
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
                ? CommandBuilder::deleteFingerprint($pin, $fid)
                : $this->fail('--pin required for delete'),
            'enroll' => $pin
                ? CommandBuilder::enrollFingerprint($pin, (int) ($fid ?? 0), (int) $this->option('retry'))
                : $this->fail('--pin required for enroll'),
            'query' => $pin
                ? CommandBuilder::queryFingerprint($pin, $fid)
                : $this->fail('--pin required for query'),
            default => $this->fail('action must be update, delete, enroll, or query'),
        };

        if ($body === null) {
            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} fingerprint/{$action} command(s).");
        $this->line($body);

        return self::SUCCESS;
    }

    private function buildUpdate(mixed $pin, ?int $fid): ?string
    {
        if (! $pin || $fid === null || ! $this->option('template')) {
            return $this->fail('--pin, --fid and --template are required for update');
        }

        return CommandBuilder::updateFingerprint(array_filter([
            'pin' => $pin,
            'fid' => $fid,
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
