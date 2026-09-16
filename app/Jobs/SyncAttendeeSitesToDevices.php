<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
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

#[Queue('attendees')]
#[Tries(3)]
#[Timeout(180)]
#[Backoff(15, 45, 90)]
class SyncAttendeeSitesToDevices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param list<string> $siteIds Sites that were added or removed
     * @param bool $revoke true = remove from devices of these sites
     */
    public function __construct(
        public string $pin,
        public array  $siteIds,
        public bool   $revoke = false,
    )
    {
    }

    public function handle(): void
    {
        if (empty($this->siteIds)) {
            return;
        }

        $serials = Device::query()
            ->whereIn('site_id', $this->siteIds)
            ->where('status', Device::STATUS_APPROVED)
            ->pluck('serial_number')
            ->all();

        if (empty($serials)) {
            Log::info('SyncAttendeeSitesToDevices: no approved devices found', [
                'pin' => $this->pin,
                'siteIds' => $this->siteIds,
            ]);
            return;
        }

        Log::info('SyncAttendeeSitesToDevices', [
            'pin' => $this->pin,
            'sites' => $this->siteIds,
            'devices' => $serials,
            'revoke' => $this->revoke,
        ]);

        SyncAttendeeToDevices::dispatch(
            $this->pin,
            $serials,
            $this->revoke   // true = send DELETE
        );
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncAttendeeSitesToDevices failed permanently', [
            'pin' => $this->pin,
            'siteIds' => $this->siteIds,
            'revoke' => $this->revoke,
            'error' => $e->getMessage(),
        ]);
    }
}
