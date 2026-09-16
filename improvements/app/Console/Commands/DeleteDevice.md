```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:delete {serial : The serial number of the device} {--force : Hard delete instead of soft delete}')]
#[Description('Delete a device by serial number')]
class DeleteDevice extends Command
{
    public function handle(): int
    {
        $serial = trim((string) $this->argument('serial'));
        $force = (bool) $this->option('force');

        $device = Device::withTrashed()->where('serial_number', $serial)->first();

        if (! $device) {
            $this->error("Device with SN '{$serial}' not found.");

            return self::FAILURE;
        }

        $label = $force ? 'permanently delete' : 'soft-delete';

        if (! $this->option('no-interaction') && ! $this->confirm("Are you sure you want to {$label} device {$serial}?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        if ($force) {
            $device->forceDelete();
            $this->info("Device {$serial} has been permanently deleted.");
        } else {
            $device->delete();
            $this->info("Device {$serial} has been soft-deleted.");
        }

        return self::SUCCESS;
    }
}
