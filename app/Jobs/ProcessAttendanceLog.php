<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AttendanceLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('attendance')]
#[Tries(3)]
#[Backoff(10, 30, 60)]
class ProcessAttendanceLog implements ShouldQueue, ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;
    public bool $deleteWhenMissingModels = true;

    // Store primitive values so uniqueId / tags / middleware never touch the model too early
    public string $logId = '';
    public string $pin = '';
    public string $deviceSerial = '';

    public function __construct(public AttendanceLog $log)
    {
        $this->logId = (string)$log->id;
        $this->pin = (string)($log->pin ?? '');
        $this->deviceSerial = (string)($log->device_serial ?? '');
    }

    public function uniqueId(): string
    {
        return 'attendance-log:' . $this->logId;
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->overlappingKey())
                ->releaseAfter(30)
                ->expireAfter(120),
        ];
    }

    public function tags(): array
    {
        return [
            'attendance',
            'log:' . $this->logId,
            'pin:' . ($this->pin ?: 'unknown'),
        ];
    }

    public function handle(): void
    {
        // Prefer the already-loaded model, otherwise re-fetch by the stored primitive ID
        $log = $this->log;

        if (!$log || !$log->exists) {
            $log = filled($this->logId)
                ? AttendanceLog::find($this->logId)
                : null;
        }

        if (!$log) {
            Log::warning('ProcessAttendanceLog skipped: log not found', [
                'log_id' => $this->logId,
                'pin' => $this->pin,
                'deviceSerial' => $this->deviceSerial,
            ]);
            return;
        }

        // Guard against a model that somehow lost its pin
        $pin = $log->pin ?? $this->pin;

        if (blank($pin)) {
            Log::warning('ProcessAttendanceLog skipped: missing PIN', [
                'log_id' => $log->id ?? $this->logId,
                'pin' => $pin,
                'deviceSerial' => $log->device_serial ?? $this->deviceSerial,
            ]);
            return;
        }

        Log::info('Processing attendance log', [
            'id' => $log->id,
            'pin' => $pin,
            'device' => $log->device_serial ?? $this->deviceSerial,
            'time' => $log->timestamp?->toDateTimeString(),
            'status' => $log->status,
        ]);

        // TODO: calculate late/early, update daily summary, notifications, payroll
    }

    public function failed(Throwable $e): void
    {
        Log::error('ProcessAttendanceLog failed permanently', [
            'log_id' => $this->logId,
            'pin' => $this->pin,
            'error' => $e->getMessage(),
        ]);
    }

    private function overlappingKey(): string
    {
        return filled($this->pin)
            ? 'attendee:' . $this->pin
            : 'attendance-log:' . $this->logId;
    }
}
