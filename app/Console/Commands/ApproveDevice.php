<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Unified device approve / block command.
 *
 * Replaces: ApproveDevice, ApproveAllDevices, BulkApproveDevices, BlockDevice.
 *
 * Examples:
 *   php artisan device:approve SN001
 *   php artisan device:approve SN001 SN002 --block --reason="lost"
 *   php artisan device:approve --all
 *   php artisan device:approve --all --dry-run
 *   php artisan device:approve SN999 --create
 */
#[Signature('device:approve
    {serials?* : One or more serial numbers (omit with --all)}
    {--all : Act on all pending devices (approve only)}
    {--block : Block instead of approve}
    {--reason= : Reason when blocking}
    {--create : Create missing serials as pending, then act}
    {--dry-run : Show targets without changing them}
    {--force : Skip confirmation}')]
#[Description('Approve or block devices by serial (or all pending with --all)')]
class ApproveDevice extends Command
{
    public function handle(): int
    {
        $block = (bool) $this->option('block');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $create = (bool) $this->option('create');
        $force = (bool) $this->option('force');
        $reason = $this->option('reason') ?: 'Blocked by admin';

        $serials = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $this->argument('serials') ?? []
        ))));

        if ($all && $block) {
            $this->error('--all cannot be combined with --block (refusing mass block).');

            return self::FAILURE;
        }

        if ($all) {
            $devices = Device::query()
                ->where('status', Device::STATUS_PENDING)
                ->orderBy('created_at')
                ->get()
                ->keyBy('serial_number');
        } elseif ($serials !== []) {
            $devices = Device::query()
                ->whereIn('serial_number', $serials)
                ->get()
                ->keyBy('serial_number');

            $missing = array_values(array_diff($serials, $devices->keys()->all()));

            if ($missing !== []) {
                $this->warn('Not found: '.implode(', ', $missing));

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
        } else {
            $this->error('Provide serial number(s) or use --all.');

            return self::FAILURE;
        }

        if ($devices->isEmpty()) {
            $this->info($all ? 'No pending devices to approve.' : 'No matching devices.');

            return self::SUCCESS;
        }

        $action = $block ? 'BLOCK' : 'APPROVE';
        $this->warn("{$action} {$devices->count()} device(s):");
        $this->table(
            ['Serial', 'Status', 'IP', 'Last Seen'],
            $devices->map(fn (Device $d) => [
                $d->serial_number,
                $d->status,
                $d->ip ?? '-',
                $d->last_seen_at?->diffForHumans() ?? 'Never',
            ])
        );

        if ($dryRun) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->option('no-interaction') && ! $this->confirm('Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($devices as $device) {
            if ($block) {
                $device->block($reason);
            } else {
                $device->approve();
            }
            $count++;
            $this->line(($block ? 'Blocked' : 'Approved').": {$device->serial_number}");
        }

        $this->info("{$action}ED {$count} device(s). Domain events fired via Device model.");

        return self::SUCCESS;
    }
}
