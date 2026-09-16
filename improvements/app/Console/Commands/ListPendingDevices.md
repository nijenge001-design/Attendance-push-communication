```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:pending')]
#[Description('List all devices waiting for approval')]
class ListPendingDevices extends Command
{
    public function handle(): int
    {
        $devices = Device::pending()->orderBy('created_at')->get();

        if ($devices->isEmpty()) {
            $this->info('No pending devices.');

            return self::SUCCESS;
        }

        $this->table(
            ['Serial Number', 'IP', 'Pushver', 'Last Seen', 'Created At'],
            $devices->map(function (Device $d) {
                $caps = $d->capabilities ?? [];

                return [
                    $d->serial_number,
                    $d->ip ?? '-',
                    $caps['pushver'] ?? ($d->pushver ?? '-'),
                    $d->last_seen_at?->diffForHumans() ?? 'Never',
                    $d->created_at?->toDateTimeString() ?? '-',
                ];
            })
        );

        $this->info('Total pending: ' . $devices->count());

        return self::SUCCESS;
    }
}
