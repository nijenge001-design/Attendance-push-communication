<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('device:show {serial : The serial number of the device}')]
#[Description('Show detailed information about a device')]
class ShowDevice extends Command
{
    public function handle(): int
    {
        $serial = trim((string) $this->argument('serial'));
        $device = Device::withTrashed()->where('serial_number', $serial)->first();

        if (! $device) {
            $this->error("Device with SN '{$serial}' not found.");

            return self::FAILURE;
        }

        $caps = $device->capabilities ?? [];

        $this->info("Device: {$device->serial_number}");
        $this->line('Status          : ' . $device->status . ($device->trashed() ? ' (soft-deleted)' : ''));
        $this->line('Approved At     : ' . ($device->approved_at?->toDateTimeString() ?? '-'));
        $this->line('IP              : ' . ($device->ip ?? '-'));
        $this->line('Last Seen       : ' . ($device->last_seen_at?->toDateTimeString() ?? 'Never'));
        $this->line('Heartbeat Src   : ' . ($device->last_heartbeat_source ?? '-'));
        $this->line('Online          : ' . ($device->isOnline() ? 'YES' : 'NO'));
        $this->line('Language        : ' . ($device->language ?? ($caps['language'] ?? '-')));
        $this->line('Device Type     : ' . ($caps['device_type'] ?? '-'));
        $this->line('Pushver (device): ' . ($caps['pushver'] ?? ($device->pushver ?? '-')));
        $this->line('Negotiated      : ' . ($caps['negotiated'] ?? '-'));
        $this->line('Handshake At    : ' . ($caps['handshake_at'] ?? '-'));
        $this->line('Created At      : ' . ($device->created_at?->toDateTimeString() ?? '-'));
        $this->line('Updated At      : ' . ($device->updated_at?->toDateTimeString() ?? '-'));

        if ($device->rejection_reason) {
            $this->warn('Rejection Reason: ' . $device->rejection_reason);
        }

        $pendingCount = $device->pendingCommands()->pending()->count();
        $readyCount = $device->pendingCommands()->readyToSend()->count();
        $this->line("Pending Commands: {$pendingCount} (ready: {$readyCount})");

        if (method_exists($device, 'attendees')) {
            $this->line('Linked Attendees: ' . $device->attendees()->count());
        }

        return self::SUCCESS;
    }
}
