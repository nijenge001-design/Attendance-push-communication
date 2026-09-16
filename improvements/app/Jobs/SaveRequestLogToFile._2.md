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

/**
 * Persists request logs under:
 *
 *   {date}/{device}[/{table}]/
 *     raw/        request body
 *     metadata/   JSON metadata (status, duration, paths, …)
 *     response/   response body (optional)
 */
class SaveRequestLogToFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $timeout = 30;

    public function __construct(
        private readonly string $rawBody,
        private readonly array $metadata,
        private readonly string $contentType,
        private readonly int $maxRawSize,
        private readonly bool $compress = false,
    ) {
        $this->onQueue(config('logging.request.queue', 'default'));
    }

    public function handle(): void
    {
        $dateDir = now()->format('Y-m-d');
        $baseName = now()->format('H-i-s') . '_' . Str::uuid()->toString();
        $extension = $this->extensionFromContentType($this->contentType);

        $deviceKey = $this->resolveDeviceKey();
        $tableKey = $this->resolveTableKey();
        $dirPrefix = $this->resolveDirPrefix($dateDir, $deviceKey);

        $disk = Storage::disk('request_logs');

        $originalBytes = strlen($this->rawBody);
        $isEmpty = $originalBytes === 0 || trim($this->rawBody) === '';

        $rawPath = null;
        $truncated = false;
        $compressed = false;
        $storedBytes = 0;

        if (! $isEmpty) {
            $rawBody = $this->rawBody;

            if ($this->maxRawSize > 0 && $originalBytes > $this->maxRawSize) {
                $rawBody = substr($rawBody, 0, $this->maxRawSize);
                $truncated = true;
            }

            $storedExtension = $extension;
            if ($this->compress && strlen($rawBody) > 1024) {
                $gz = gzencode($rawBody, 6);
                if ($gz !== false && $gz !== '') {
                    $rawBody = $gz;
                    $storedExtension = $extension . '.gz';
                    $compressed = true;
                }
            }

            if ($rawBody !== '' && $rawBody !== false) {
                $rawPath = "{$dirPrefix}/raw/{$baseName}.{$storedExtension}";
                $disk->put($rawPath, $rawBody);
                $storedBytes = strlen($rawBody);
            }
        } elseif (! (bool) config('logging.request.save_empty_metadata', false)) {
            // No request body and empty-metadata disabled — still may save response alone
            if (! $this->shouldSaveResponse()) {
                return;
            }
        }

        $responsePath = $this->saveResponseBody($disk, $dirPrefix, $baseName);

        $meta = array_merge($this->metadata, [
            'device_key' => $deviceKey,
            'table_key' => $tableKey,
            'raw_file' => $rawPath,
            'raw_bytes_original' => $originalBytes,
            'raw_bytes_stored' => $storedBytes,
            'truncated' => $truncated,
            'compressed' => $compressed,
            'empty_body' => $isEmpty,
            'content_type' => $this->contentType,
            'response_file' => $responsePath,
            'saved_at' => now()->toIso8601String(),
        ]);

        // Avoid duplicating large response body inside metadata JSON when stored as file
        if ($responsePath !== null && isset($meta['response']['body'])) {
            $meta['response']['body'] = '[stored in response_file]';
        }

        $this->saveMetadata($disk, $dirPrefix, $baseName, $meta);
    }

    /**
     * Write response body under response/ when present and enabled.
     *
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     */
    private function saveResponseBody($disk, string $dirPrefix, string $baseName): ?string
    {
        if (! $this->shouldSaveResponse()) {
            return null;
        }

        $body = data_get($this->metadata, 'response.body');
        if (! is_string($body) || $body === '' || $body === '[non-string]') {
            return null;
        }

        // Strip truncation suffix marker content is still fine to store as-is
        $max = (int) config('logging.request.max_response_body_size', 64_000);
        if (strlen($body) > $max) {
            $body = substr($body, 0, $max);
        }

        if (trim($body) === '') {
            return null;
        }

        $path = "{$dirPrefix}/response/{$baseName}.txt";
        $disk->put($path, $body);

        return $path;
    }

    private function shouldSaveResponse(): bool
    {
        return (bool) config('logging.request.save_response_to_file', true)
            && (bool) config('logging.request.log_response_body', false);
    }

    private function resolveDeviceKey(): string
    {
        $candidates = [
            data_get($this->metadata, 'query.SN'),
            data_get($this->metadata, 'query.sn'),
            data_get($this->metadata, 'body.SN'),
            data_get($this->metadata, 'body.sn'),
            data_get($this->metadata, 'sn'),
            data_get($this->metadata, 'serial_number'),
        ];

        foreach ($candidates as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $sn = trim((string) $value);
            if ($sn === '') {
                continue;
            }
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $sn) ?? '';
            if ($safe !== '') {
                return $safe;
            }
        }

        return '_unknown';
    }

    private function resolveTableKey(): ?string
    {
        $candidates = [
            data_get($this->metadata, 'query.table'),
            data_get($this->metadata, 'query.Table'),
            data_get($this->metadata, 'body.table'),
            data_get($this->metadata, 'body.Table'),
            data_get($this->metadata, 'table'),
        ];

        foreach ($candidates as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $table = strtoupper(trim((string) $value));
            if ($table === '') {
                continue;
            }
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $table) ?? '';
            if ($safe !== '') {
                return $safe;
            }
        }

        return null;
    }

    private function resolveDirPrefix(string $dateDir, string $deviceKey): string
    {
        $prefix = "{$dateDir}/{$deviceKey}";
        $table = $this->resolveTableKey();
        if ($table !== null) {
            $prefix .= '/' . $table;
        }

        return $prefix;
    }

    /**
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     * @param  array<string, mixed>  $metadata
     */
    private function saveMetadata($disk, string $dirPrefix, string $baseName, array $metadata): void
    {
        try {
            $json = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            $disk->put("{$dirPrefix}/metadata/{$baseName}.json", $json);
        } catch (JsonException $e) {
            Log::warning('Failed to encode request metadata', [
                'error' => $e->getMessage(),
                'base' => $baseName,
                'request_id' => $metadata['request_id'] ?? null,
            ]);
        }
    }

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
