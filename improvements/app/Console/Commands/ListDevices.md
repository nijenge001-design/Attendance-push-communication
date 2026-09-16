```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:list {--status= : Filter by status (pending, approved, blocked)} {--limit=50 : Number of records to show} {--online : Only online devices} {--threshold=5 : Minutes for online check}')]
#[Description('List devices')]
class ListDevices extends Command
{
    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $query = Device::query()->orderByDesc('last_seen_at')->orderByDesc('created_at');

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        if ($this->option('online')) {
            $query->where('last_seen_at', '>=', now()->subMinutes($threshold));
        }

        $devices = $query->limit((int) $this->option('limit'))->get();

        if ($devices->isEmpty()) {
            $this->warn('No devices found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Serial', 'Status', 'Online', 'Pushver', 'Negotiated', 'IP', 'Last Seen', 'Approved At'],
            $devices->map(function (Device $d) use ($threshold) {
                $caps = $d->capabilities ?? [];

                return [
                    $d->serial_number,
                    $d->status,
                    $d->isOnline($threshold) ? 'YES' : 'NO',
                    $caps['pushver'] ?? ($d->pushver ?? '-'),
                    $caps['negotiated'] ?? '-',
                    $d->ip ?? '-',
                    $d->last_seen_at?->diffForHumans() ?? 'Never',
                    $d->approved_at?->toDateTimeString() ?? '-',
                ];
            })
        );

        $this->info('Total shown: ' . $devices->count());

        return self::SUCCESS;
    }
}
