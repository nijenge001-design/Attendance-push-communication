<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BioTemplate;
use App\Models\Device;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('attendees')]
#[Tries(3)]
#[Backoff(10, 30)]
class SyncAttendeeBioToDevice implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public BioTemplate $template,
        public ?string     $targetSerial = null,
    )
    {
    }

    public function uniqueId(): string
    {
        $target = $this->targetSerial ?? $this->template->device_serial;
        return "sync-bio:{$this->template->id}:{$target}";
    }

    public function handle(DeviceCommandQueue $queue): void
    {
        $serial = $this->targetSerial ?? $this->template->device_serial;

        $device = Device::where('serial_number', $serial)->first();

        if (!$device?->isApproved()) {
            return;
        }

        $cmd = match ((int)$this->template->type) {
            BioTemplate::TYPE_FINGER => CommandBuilder::updateFingerprint(
                $this->template->pin,
                (int)$this->template->no,
                $this->template->template_data,
                (int)$this->template->valid
            ),
            BioTemplate::TYPE_FACE,
            BioTemplate::TYPE_VISIBLE_FACE => CommandBuilder::updateFace(
                $this->template->pin,
                (int)$this->template->no,
                $this->template->template_data,
                (int)$this->template->valid
            ),
            default => CommandBuilder::updateBioData([
                'pin' => $this->template->pin,
                'no' => $this->template->no,
                'index' => $this->template->index,
                'valid' => $this->template->valid,
                'duress' => $this->template->duress,
                'type' => $this->template->type,
                'majorver' => $this->template->major_ver,
                'minorver' => $this->template->minor_ver,
                'format' => $this->template->format,
                'tmp' => $this->template->template_data,
            ]),
        };

        $queue->queue($serial, $cmd);
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncAttendeeBioToDevice failed permanently', [
            'template_id' => $this->template->id,
            'target' => $this->targetSerial,
            'error' => $e->getMessage(),
        ]);
    }
}
