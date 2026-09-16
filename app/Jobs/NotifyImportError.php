<?php

declare(strict_types=1);

namespace App\Jobs;

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

#[Queue('notifications')]
#[Tries(3)]
#[Backoff(20, 60)]
class NotifyImportError implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;
    public bool $deleteWhenMissingModels = true;

    public function __construct(public AttendeeImportError $error)
    {
    }

    public function uniqueId(): string
    {
        return 'notify-import-error:' . $this->error->id;
    }

    public function tags(): array
    {
        return [
            'notification',
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

        Log::warning('Sending import error notification', [
            'error_id' => $this->error->id,
            'device' => $this->error->device_serial,
            'pin' => $this->error->pin,
            'code' => $this->error->error_code,
            'message' => $this->error->error_message,
        ]);

        // TODO: Notify managers who have access to this device/site
        // Example:
        // $managers = User::role('manager')
        //     ->whereHas('sites.devices', fn ($q) => $q->where('serial_number', $this->error->device_serial))
        //     ->get();
        //
        // Notification::send($managers, new \App\Notifications\AttendeeImportErrorNotification($this->error));
    }

    public function failed(Throwable $e): void
    {
        Log::error('NotifyImportError failed permanently', [
            'error_id' => $this->error->id,
            'error' => $e->getMessage(),
        ]);
    }
}
