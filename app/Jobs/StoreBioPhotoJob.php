<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Queue('photos')]
#[Tries(3)]
#[Timeout(120)]
#[Backoff(10, 30)]
class StoreBioPhotoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(
        public string $sn,
        public string $pin,
        public string $filename,
        public int    $type,
        public string $base64,
    )
    {
    }

    public function uniqueId(): string
    {
        return "photo:{$this->sn}:{$this->filename}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("photo-store:{$this->sn}"))
                ->releaseAfter(15)
                ->expireAfter(120),
        ];
    }

    public function handle(): void
    {
        $path = "photos/{$this->sn}/{$this->filename}";

        if ($this->base64 !== '') {
            Storage::disk('local')->put($path, base64_decode($this->base64));
        }

        Photo::updateOrCreate(
            [
                'device_serial' => $this->sn,
                'pin' => $this->pin,
                'filename' => $this->filename,
                'type' => $this->type,
            ],
            [
                'content' => null,
                'url' => $path,
            ]
        );

        Log::channel('user_info')->info('BIOPHOTO stored', [
            'sn' => $this->sn,
            'pin' => $this->pin,
            'file' => $this->filename,
            'type' => $this->type,
            'path' => $path,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('user_info')->error('StoreBioPhotoJob failed permanently', [
            'sn' => $this->sn,
            'pin' => $this->pin,
            'file' => $this->filename,
            'error' => $e->getMessage(),
        ]);
    }
}
