```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:bulk-approve {serials* : One or more serial numbers} {--block : Block instead of approve} {--reason= : Reason when blocking} {--create : Create missing serials as pending then act}')]
#[Description('Approve (or block) one or more devices by serial number')]
class BulkApproveDevices extends Command
{
    public function handle(): int
    {
        $serials = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $this->argument('serials') ?? []
        ))));
        $block = (bool) $this->option('block');
        $reason = $this->option('reason') ?: 'Blocked by admin';
        $create = (bool) $this->option('create');

        if ($serials === []) {
            $this->error('At least one serial number is required.');

            return self::FAILURE;
        }

        $devices = Device::whereIn('serial_number', $serials)->get()->keyBy('serial_number');
        $missing = array_values(array_diff($serials, $devices->keys()->all()));

        if ($missing !== []) {
            $this->warn('Not found: ' . implode(', ', $missing));

            if ($create) {
                foreach ($missing as $sn) {
                    $devices[$sn] = Device::create([
                        'serial_number' => $sn,
                        'status' => Device::STATUS_PENDING,
                    ]);
                    $this->line("Created pending device: {$sn}");
                }
            }
        }

        if ($devices->isEmpty()) {
            $this->error('No matching devices found.');

            return self::FAILURE;
        }

        $action = $block ? 'BLOCK' : 'APPROVE';
        $this->warn("About to {$action} {$devices->count()} device(s).");

        if (! $this->option('no-interaction') && ! $this->confirm('Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($devices as $device) {
            if ($block) {
                method_exists($device, 'block')
                    ? $device->block($reason)
                    : $device->forceFill([
                        'status' => Device::STATUS_BLOCKED,
                        'approved_at' => null,
                        'rejection_reason' => $reason,
                    ])->save();
            } else {
                method_exists($device, 'approve')
                    ? $device->approve()
                    : $device->forceFill([
                        'status' => Device::STATUS_APPROVED,
                        'approved_at' => now(),
                        'rejection_reason' => null,
                    ])->save();
            }
            $count++;
        }

        $this->info("{$action}ED {$count} device(s).");

        return self::SUCCESS;
    }
}
