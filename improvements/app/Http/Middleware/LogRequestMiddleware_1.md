
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\SaveRequestLogToFile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs incoming requests (and optionally the response) with sanitization,
 * path filtering, and optional asynchronous raw-body persistence.
 *
 * Implements terminable behaviour so response status + duration can be
 * recorded after the response has been sent.
 */
class LogRequestMiddleware
{
    /** @var array<string, mixed>|null */
    protected ?array $metadata = null;

    protected float $startedAt = 0.0;

    protected ?string $requestId = null;

    protected ?string $rawBody = null;

    protected bool $shouldSaveToFile = false;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldLog($request)) {
            return $next($request);
        }

        $this->startedAt = microtime(true);
        $this->requestId = (string) Str::uuid();

        try {
            $this->metadata = $this->buildMetadata($request);
            $this->logRequest($this->metadata);

            if ($this->shouldSaveToFile()) {
                $this->shouldSaveToFile = true;
                // Capture raw body early (stream may be consumed later)
                $this->rawBody = $request->getContent();
            }
        } catch (Throwable $e) {
            Log::warning('LogRequestMiddleware failed during handle', [
                'error' => $e->getMessage(),
                'request_id' => $this->requestId,
            ]);
        }

        return $next($request);
    }

    /**
     * Runs after the response has been sent to the client.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->metadata === null) {
            return;
        }

        try {
            $durationMs = round((microtime(true) - $this->startedAt) * 1000, 2);

            $this->metadata['response'] = [
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ];

            // Re-log with response info when configured
            if ((bool) config('logging.request.log_response', true)) {
                $this->logRequest($this->metadata, 'Request completed');
            }

            if ($this->shouldSaveToFile && $this->rawBody !== null) {
                $maxSize = (int) config('logging.request.max_raw_body_size', 1_048_576);

                dispatch(new SaveRequestLogToFile(
                    rawBody: $this->rawBody,
                    metadata: $this->metadata,
                    contentType: $request->header('Content-Type', 'text/plain'),
                    maxRawSize: $maxSize,
                    compress: (bool) config('logging.request.compress_raw', false),
                ));
            }
        } catch (Throwable $e) {
            Log::warning('LogRequestMiddleware failed during terminate', [
                'error' => $e->getMessage(),
                'request_id' => $this->requestId,
            ]);
        }
    }

    protected function shouldLog(Request $request): bool
    {
        if (! (bool) config('logging.request.enabled', false)) {
            return false;
        }

        $skipPaths = config('logging.request.skip_paths', ['up', 'health', 'horizon*', 'telescope*', '_debugbar*']);
        foreach ($skipPaths as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        $onlyPaths = config('logging.request.only_paths', []);
        if (is_string($onlyPaths)) {
            $onlyPaths = array_values(array_filter(array_map('trim', explode(',', $onlyPaths))));
        }

        if (! empty($onlyPaths)) {
            $match = false;
            foreach ($onlyPaths as $pattern) {
                if ($request->is($pattern)) {
                    $match = true;
                    break;
                }
            }
            if (! $match) {
                return false;
            }
        }

        $methods = config('logging.request.methods', ['*']);
        if (! in_array('*', $methods, true) && ! in_array(strtoupper($request->method()), array_map('strtoupper', $methods), true)) {
            return false;
        }

        return true;
    }

    protected function shouldSaveToFile(): bool
    {
        return (bool) config('logging.request.save_to_file', false);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function logRequest(array $metadata, string $message = 'Incoming Request'): void
    {
        $channel = config('logging.request.channel', config('logging.default', 'stack'));
        $level = config('logging.request.level', 'info');

        Log::channel($channel)->log($level, $message, $metadata);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetadata(Request $request): array
    {
        $includeBody = (bool) config('logging.request.include_parsed_body', true);

        $data = [
            'request_id' => $this->requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query' => $this->sanitize($request->query->all()),
            'files' => array_keys($request->allFiles()),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($includeBody) {
            $parsed = [];
            $maxKeys = (int) config('logging.request.max_parsed_keys', 50);
            $keyCount = 0;

            foreach ($request->keys() as $key) {
                if ($keyCount >= $maxKeys) {
                    $parsed['_truncated_keys'] = true;
                    break;
                }

                $value = $request->input($key);
                $parsed[$key] = $this->sanitizeValue($value);
                $keyCount++;
            }

            $data['body'] = $parsed;
        }

        return $data;
    }

    /**
     * Recursively sanitize a value.
     */
    protected function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $k = (string) $k;
                if ($this->isSensitive($k)) {
                    $result[$k] = '********';
                } else {
                    $result[$k] = $this->sanitizeValue($v);
                }
            }

            return $result;
        }

        if (is_string($value)) {
            $maxLen = (int) config('logging.request.max_string_length', 2000);
            if (strlen($value) > $maxLen) {
                return substr($value, 0, 200) . '... [len=' . strlen($value) . ']';
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitize(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ($this->isSensitive($key)) {
                $result[$key] = '********';
            } else {
                $result[$key] = $this->sanitizeValue($value);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, array<int, string|null>>  $headers
     * @return array<string, array<int, string|null>>
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = config('logging.request.sensitive_headers', [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
        ]);

        $maxHeaderLength = (int) config('logging.request.max_header_length', 500);

        foreach ($headers as $key => $value) {
            $lower = strtolower((string) $key);

            if (in_array($lower, $sensitiveHeaders, true)) {
                $headers[$key] = ['********'];
                continue;
            }

            // Truncate very long header values
            if (is_array($value)) {
                $headers[$key] = array_map(function ($v) use ($maxHeaderLength) {
                    if (is_string($v) && strlen($v) > $maxHeaderLength) {
                        return substr($v, 0, 100) . '... [len=' . strlen($v) . ']';
                    }

                    return $v;
                }, $value);
            }
        }

        return $headers;
    }

    protected function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        $sensitive = config('logging.request.sensitive_keys', [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
            'secret',
            'authorization',
            'credit_card',
            'cvv',
            'ssn',
        ]);

        foreach ($sensitive as $field) {
            if (str_contains($key, strtolower((string) $field))) {
                return true;
            }
        }

        return false;
    }
}
```
