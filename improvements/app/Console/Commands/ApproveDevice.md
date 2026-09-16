```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:approve {serial : The serial number of the device} {--block : Block the device instead of approving it} {--reason= : Reason for blocking (optional)}')]
#[Description('Approve or block a device by serial number')]
class ApproveDevice extends Command
{
    public function handle(): int
    {
        $serial = trim((string) $this->argument('serial'));
        $block = (bool) $this->option('block');
        $reason = $this->option('reason');

        $device = Device::firstOrNew(['serial_number' => $serial]);

        if (! $device->exists) {
            $this->warn("Device SN '{$serial}' does not exist yet — creating as pending first.");
            $device->status = Device::STATUS_PENDING;
            $device->save();
        }

        if ($block) {
            if (method_exists($device, 'block')) {
                $device->block($reason ?: 'Blocked by admin');
            } else {
                $device->forceFill([
                    'status' => Device::STATUS_BLOCKED,
                    'approved_at' => null,
                    'rejection_reason' => $reason ?: 'Blocked by admin',
                ])->save();
            }

            $this->info("Device {$serial} has been BLOCKED." . ($reason ? " Reason: {$reason}" : ''));

            return self::SUCCESS;
        }

        // Prefer model method so DeviceObserver queues CHECK / DateTime / INFO
        if (method_exists($device, 'approve')) {
            $device->approve();
        } else {
            $device->forceFill([
                'status' => Device::STATUS_APPROVED,
                'approved_at' => now(),
                'rejection_reason' => null,
            ])->save();
        }

        $this->info("Device {$serial} has been APPROVED.");

        $pending = $device->pendingCommands()->pending()->count();
        if ($pending > 0) {
            $this->line("Pending commands waiting: {$pending}");
        }

        return self::SUCCESS;
    }
}
