<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attendee;
use App\Models\Device;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue USERINFO (create/update) or DELETE commands for one attendee
 * on one or more devices.
 *
 * Uses pin + serials as primitives so the job stays valid even if the
 * Attendee model is soft-deleted before the worker runs.
 */
#[Queue('attendees')]
#[Tries(3)]
#[Timeout(120)]
#[Backoff(10, 30, 60)]
class SyncAttendeeToDevices implements ShouldQueue, ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Collapse duplicate syncs for the same pin/delete flag within 2 minutes. */
    public int $uniqueFor = 120;

    /**
     * @param list<string> $deviceSerials empty = all active devices of the attendee
     */
    public function __construct(
        public string $pin,
        public array  $deviceSerials = [],
        public bool   $delete = false,
    )
    {
    }

    public function uniqueId(): string
    {
        $serials = implode(',', $this->deviceSerials);
        $action = $this->delete ? 'delete' : 'upsert';

        return "sync-attendee:{$this->pin}:{$action}:{$serials}";
    }

    public function tags(): array
    {
        return [
            'sync-attendee',
            'pin:' . $this->pin,
            $this->delete ? 'action:delete' : 'action:upsert',
        ];
    }

    public function handle(DeviceCommandQueue $queue): void
    {
        $serials = $this->resolveSerials();

        if ($serials === []) {
            Log::info('SyncAttendeeToDevices: no target devices', [
                'pin' => $this->pin,
                'delete' => $this->delete,
            ]);
            return;
        }

        // For upsert we need the attendee row; for delete we only need the pin.
        $attendee = $this->delete
            ? null
            : Attendee::query()->where('pin', $this->pin)->first();

        if (!$this->delete && !$attendee) {
            Log::warning('SyncAttendeeToDevices: attendee not found', [
                'pin' => $this->pin,
            ]);
            return;
        }

        $command = $this->buildCommand($attendee);
        $queued = 0;

        foreach ($serials as $serial) {
            try {
                $device = Device::query()->where('serial_number', $serial)->first();

                // Never push to pending/blocked devices.
                if (!$device?->isApproved()) {
                    Log::debug('SyncAttendeeToDevices: skip non-approved device', [
                        'pin' => $this->pin,
                        'device' => $serial,
                        'status' => $device?->status,
                    ]);
                    continue;
                }

                $queue->queue($serial, $command);
                $queued++;
            } catch (Throwable $e) {
                // Per-device failure must not abort the whole batch.
                Log::error('SyncAttendeeToDevices: device failed', [
                    'pin' => $this->pin,
                    'device' => $serial,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SyncAttendeeToDevices done', [
            'pin' => $this->pin,
            'delete' => $this->delete,
            'targets' => count($serials),
            'queued' => $queued,
        ]);
    }

    protected function buildCommand(?Attendee $attendee): string
    {
        if ($this->delete) {
            return CommandBuilder::deleteUser($this->pin);
        }

        return CommandBuilder::updateUser([
            'pin' => $attendee->pin,
            'name' => $attendee->name ?? '',
            'pri' => $attendee->privilege,
            'passwd' => $attendee->password,
            'card' => $attendee->card_number,
            'grp' => $attendee->group_id,
            'tz' => $attendee->timezone,
            'verify' => $attendee->verification_mode,
            'vicecard' => $attendee->vice_card,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function resolveSerials(): array
    {
        if ($this->deviceSerials !== []) {
            return array_values(array_unique($this->deviceSerials));
        }

        // Delete without an explicit list is unsafe — we refuse rather than guess.
        if ($this->delete) {
            Log::warning('SyncAttendeeToDevices: delete without device list', [
                'pin' => $this->pin,
            ]);
            return [];
        }

        return Attendee::query()
            ->where('pin', $this->pin)
            ->first()
            ?->devices()
            ->wherePivot('active', true)
            ->pluck('serial_number')
            ->all() ?? [];
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncAttendeeToDevices failed permanently', [
            'pin' => $this->pin,
            'delete' => $this->delete,
            'error' => $e->getMessage(),
        ]);
    }
}
