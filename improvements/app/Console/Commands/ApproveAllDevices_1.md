```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:approve-all {--dry-run : Show what would be approved without updating} {--force : Skip confirmation}')]
#[Description('Approve all pending devices')]
class ApproveAllDevices extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $noInteraction = (bool) $this->option('no-interaction');

        $pending = Device::pending()->orderBy('created_at')->get();

        if ($pending->isEmpty()) {
            $this->info('No pending devices to approve.');

            return self::SUCCESS;
        }

        $this->warn("Found {$pending->count()} pending device(s).");

        if ($dryRun) {
            $this->table(
                ['Serial Number', 'IP', 'Last Seen', 'Created At'],
                $pending->map(fn (Device $d) => [
                    $d->serial_number,
                    $d->ip ?? '-',
                    $d->last_seen_at?->diffForHumans() ?? 'Never',
                    $d->created_at?->toDateTimeString() ?? '-',
                ])
            );

            return self::SUCCESS;
        }

        if (! $force && ! $noInteraction) {
            if (! $this->confirm("Approve all {$pending->count()} devices?")) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        } else {
            $this->line('Skipping confirmation (force or no-interaction).');
        }

        $count = 0;
        foreach ($pending as $device) {
            if (method_exists($device, 'approve')) {
                $device->approve();
            } else {
                $device->forceFill([
                    'status' => Device::STATUS_APPROVED,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ])->save();
            }
            $count++;
        }

        $this->info("Approved {$count} device(s). Observer should have queued onboarding commands.");

        return self::SUCCESS;
    }
}
