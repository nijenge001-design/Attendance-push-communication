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

#[Signature('device:photo
    {action : update-user|delete-user|update-bio|delete-bio}
    {--serial= : Target one device SN}
    {--status=approved : Target devices by status when no --serial}
    {--all : All devices}
    {--pin= : User PIN}
    {--content= : Base64 or raw photo content}
    {--filename= : Optional filename}
    {--size= : Optional size}
    {--force : Skip confirmation}')]
#[Description('Biometric / user photos: update or delete user photo or bio photo')]
class PhotoCommand extends Command
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

        $body = match ($action) {
            'update-user' => $this->buildUserUpdate($pin),
            'delete-user' => $pin
                ? CommandBuilder::deleteUserPhoto($pin)
                : $this->fail('--pin required for delete-user'),
            'update-bio' => $this->buildBioUpdate($pin),
            'delete-bio' => $pin
                ? CommandBuilder::deleteBioPhoto($pin)
                : $this->fail('--pin required for delete-bio'),
            default => $this->fail('action must be update-user, delete-user, update-bio, or delete-bio'),
        };

        if ($body === null) {
            return self::FAILURE;
        }

        if (count($serials) > 1 && ! $this->confirmBroadcast(count($serials), 1)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $total = $this->queueToSerials($queue, $serials, $body);
        $this->info("Queued {$total} photo/{$action} command(s).");
        $this->line(\Illuminate\Support\Str::limit($body, 120));

        return self::SUCCESS;
    }

    private function buildUserUpdate(mixed $pin): ?string
    {
        if (! $pin || ! $this->option('content')) {
            return $this->fail('--pin and --content are required for update-user');
        }

        return CommandBuilder::updateUserPhoto(array_filter([
            'pin' => $pin,
            'content' => $this->option('content'),
            'filename' => $this->option('filename'),
            'size' => $this->option('size'),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function buildBioUpdate(mixed $pin): ?string
    {
        if (! $pin || ! $this->option('content')) {
            return $this->fail('--pin and --content are required for update-bio');
        }

        return CommandBuilder::updateBioPhoto(array_filter([
            'pin' => $pin,
            'content' => $this->option('content'),
            'filename' => $this->option('filename'),
            'size' => $this->option('size'),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function fail(string $message): null
    {
        $this->error($message);

        return null;
    }
}
