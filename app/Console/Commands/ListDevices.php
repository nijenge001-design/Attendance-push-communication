<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * List devices with common filters.
 * Replaces ListPendingDevices (use --status=pending or --pending).
 */
#[Signature('device:list
    {--status= : Filter by status (pending, approved, blocked)}
    {--pending : Shortcut for --status=pending}
    {--online : Only online devices}
    {--offline : Only offline devices}
    {--search= : Match serial, name, or IP}
    {--limit=50 : Max rows}
    {--threshold=5 : Minutes for online/offline check}')]
#[Description('List devices (filter by status, online/offline, search)')]
class ListDevices extends Command
{
    public function handle(): int
    {
        $threshold = max(1, (int) $this->option('threshold'));
        $query = Device::query()->orderByDesc('last_seen_at')->orderByDesc('created_at');

        $status = $this->option('status');
        if ($this->option('pending')) {
            $status = Device::STATUS_PENDING;
        }
        if ($status) {
            $query->where('status', $status);
        }

        if ($this->option('online') && $this->option('offline')) {
            $this->error('Use only one of --online or --offline.');

            return self::FAILURE;
        }

        if ($this->option('online')) {
            $query->where('last_seen_at', '>=', now()->subMinutes($threshold));
        }

        if ($this->option('offline')) {
            $query->where(function ($q) use ($threshold) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subMinutes($threshold));
            });
        }

        if ($search = trim((string) $this->option('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        $devices = $query->limit(max(1, (int) $this->option('limit')))->get();

        if ($devices->isEmpty()) {
            $this->warn('No devices found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Serial', 'Name', 'Status', 'Online', 'IP', 'Site', 'Last Seen', 'Approved At'],
            $devices->map(function (Device $d) use ($threshold) {
                return [
                    $d->serial_number,
                    $d->device_name ?? '-',
                    $d->status,
                    $d->isOnline($threshold) ? 'YES' : 'NO',
                    $d->ip ?? '-',
                    $d->site_id ?? '-',
                    $d->last_seen_at?->diffForHumans() ?? 'Never',
                    $d->approved_at?->toDateTimeString() ?? '-',
                ];
            })
        );

        $this->info('Shown: '.$devices->count());

        return self::SUCCESS;
    }
}
