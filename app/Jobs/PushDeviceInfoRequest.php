<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('devices')]
#[Tries(3)]
class PushDeviceInfoRequest implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 120;
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Device $device)
    {
    }

    public function uniqueId(): string
    {
        return 'device-info-request:' . $this->device->serial_number;
    }

    public function handle(DeviceCommandQueue $queue): void
    {
        if (!$this->device->isApproved()) {
            return;
        }

        $queue->queue(
            $this->device->serial_number,
            CommandBuilder::info()
        );
    }

    public function failed(Throwable $e): void
    {
        Log::error('PushDeviceInfoRequest failed', [
            'device' => $this->device->serial_number,
            'error' => $e->getMessage(),
        ]);
    }
}
