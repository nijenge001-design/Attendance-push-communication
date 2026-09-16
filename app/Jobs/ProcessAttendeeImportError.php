<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attendee;
use App\Models\AttendeeImportError;
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
#[Backoff(15, 45)]
class ProcessAttendeeImportError implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;
    public bool $deleteWhenMissingModels = true;

    public function __construct(public AttendeeImportError $error)
    {
    }

    public function uniqueId(): string
    {
        return 'process-import-error:' . $this->error->id;
    }

    public function tags(): array
    {
        return [
            'import-error',
            'device:' . $this->error->device_serial,
            'pin:' . $this->error->pin,
        ];
    }

    public function handle(): void
    {
        if ($this->error->status !== AttendeeImportError::STATUS_PENDING) {
            return;
        }

        Log::info('Processing attendee import error', [
            'error_id' => $this->error->id,
            'code' => $this->error->error_code,
            'pin' => $this->error->pin,
            'device' => $this->error->device_serial,
        ]);

        // Example auto-resolution strategies
        match ($this->error->error_code) {
            'duplicate_card' => $this->handleDuplicateCard(),
            'duplicate_pin' => $this->handleDuplicatePin(),
            default => $this->leaveForManualReview(),
        };
    }

    protected function handleDuplicateCard(): void
    {
        // Example: ignore if the existing attendee already has this card
        $existing = Attendee::where('card_number', $this->error->card_number)->first();

        if ($existing) {
            $this->error->markIgnored('Auto-ignored: card already exists on PIN ' . $existing->pin);
            Log::info('Import error auto-ignored (duplicate card)', [
                'error_id' => $this->error->id,
            ]);
            return;
        }

        $this->leaveForManualReview();
    }

    protected function handleDuplicatePin(): void
    {
        $this->leaveForManualReview();
    }

    protected function leaveForManualReview(): void
    {
        // Optionally dispatch a notification
        NotifyImportError::dispatch($this->error);
    }

    public function failed(Throwable $e): void
    {
        Log::error('ProcessAttendeeImportError failed permanently', [
            'error_id' => $this->error->id,
            'error' => $e->getMessage(),
        ]);
    }
}
