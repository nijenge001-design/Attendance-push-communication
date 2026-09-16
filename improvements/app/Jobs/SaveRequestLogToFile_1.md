```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class SaveRequestLogToFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Number of times the job may be attempted. */
    public int $tries = 3;

    /** Backoff in seconds between retries. */
    public array $backoff = [5, 15, 30];

    /** Seconds the job may run before timing out. */
    public int $timeout = 30;

    public function __construct(
        private readonly string $rawBody,
        private readonly array $metadata,
        private readonly string $contentType,
        private readonly int $maxRawSize,
        private readonly bool $compress = false,
    ) {
        // Prefer a dedicated queue when available
        $this->onQueue(config('logging.request.queue', 'default'));
    }

    public function handle(): void
    {
        $dateDir = now()->format('Y-m-d');
        $baseName = now()->format('H-i-s') . '_' . Str::uuid()->toString();
        $extension = $this->extensionFromContentType($this->contentType);

        $disk = Storage::disk('request_logs');

        // Truncate if needed
        $rawBody = $this->rawBody;
        $truncated = false;
        $originalBytes = strlen($this->rawBody);

        if ($this->maxRawSize > 0 && $originalBytes > $this->maxRawSize) {
            $rawBody = substr($rawBody, 0, $this->maxRawSize);
            $truncated = true;
        }

        // Optional gzip compression for large bodies
        $storedExtension = $extension;
        if ($this->compress && $originalBytes > 1024) {
            $compressed = gzencode($rawBody, 6);
            if ($compressed !== false) {
                $rawBody = $compressed;
                $storedExtension = $extension . '.gz';
            }
        }

        $rawPath = "{$dateDir}/raw/{$baseName}.{$storedExtension}";
        $disk->put($rawPath, $rawBody);

        // Enrich metadata
        $metadata = $this->metadata;
        $metadata['raw_file'] = $rawPath;
        $metadata['raw_bytes_original'] = $originalBytes;
        $metadata['raw_bytes_stored'] = strlen($rawBody);
        $metadata['truncated'] = $truncated;
        $metadata['compressed'] = $this->compress && str_ends_with($storedExtension, '.gz');
        $metadata['content_type'] = $this->contentType;
        $metadata['saved_at'] = now()->toIso8601String();

        // Persist metadata as pretty JSON
        try {
            $json = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            $metaPath = "{$dateDir}/metadata/{$baseName}.json";
            $disk->put($metaPath, $json);
        } catch (JsonException $e) {
            Log::warning('Failed to encode request metadata', [
                'error' => $e->getMessage(),
                'base' => $baseName,
                'request_id' => $metadata['request_id'] ?? null,
            ]);
        }
    }

    /**
     * Handle a permanent job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SaveRequestLogToFile job failed permanently', [
            'error' => $exception?->getMessage(),
            'request_id' => $this->metadata['request_id'] ?? null,
            'path' => $this->metadata['path'] ?? null,
            'bytes' => strlen($this->rawBody),
        ]);
    }

    private function extensionFromContentType(string $contentType): string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        return match (true) {
            str_contains($contentType, 'application/json') => 'json',
            str_contains($contentType, 'xml') => 'xml',
            str_contains($contentType, 'text/plain') => 'txt',
            str_contains($contentType, 'octet-stream') => 'bin',
            str_contains($contentType, 'multipart') => 'multipart',
            str_contains($contentType, 'urlencoded') => 'txt',
            default => 'raw',
        };
    }
}
```
