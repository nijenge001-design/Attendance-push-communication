<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BioTemplate;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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

#[Queue('attendance')]
#[Tries(3)]
#[Timeout(120)]
#[Backoff(10, 30)]
class ProcessBioTemplate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;
    public bool $deleteWhenMissingModels = true;

    public function __construct(public BioTemplate $template)
    {
    }

    public function uniqueId(): string
    {
        return 'bio-template:' . $this->template->id;
    }

    public function handle(): void
    {
        Log::info('Processing bio template', [
            'id' => $this->template->id,
            'pin' => $this->template->pin,
            'type' => $this->template->type,
            'device' => $this->template->device_serial,
        ]);

        // TODO: validate template, update attendee bio status
    }

    public function failed(Throwable $e): void
    {
        Log::error('ProcessBioTemplate failed permanently', [
            'template_id' => $this->template->id,
            'error' => $e->getMessage(),
        ]);
    }
}
