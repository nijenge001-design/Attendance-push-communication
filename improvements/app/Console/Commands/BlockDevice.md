```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:block {serial : The serial number of the device} {--reason= : Reason for blocking}')]
#[Description('Block a device by serial number')]
class BlockDevice extends Command
{
    public function handle(): int
    {
        $serial = trim((string) $this->argument('serial'));
        $reason = $this->option('reason') ?: 'Blocked by admin';

        $device = Device::where('serial_number', $serial)->first();

        if (! $device) {
            $this->error("Device with SN '{$serial}' not found.");

            return self::FAILURE;
        }

        if (method_exists($device, 'block')) {
            $device->block($reason);
        } else {
            $device->forceFill([
                'status' => Device::STATUS_BLOCKED,
                'approved_at' => null,
                'rejection_reason' => $reason,
            ])->save();
        }

        $this->info("Device {$serial} has been BLOCKED.");
        $this->line("Reason: {$reason}");

        return self::SUCCESS;
    }
}
