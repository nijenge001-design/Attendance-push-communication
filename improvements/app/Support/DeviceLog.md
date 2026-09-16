```php
<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class DeviceLog
{
    /**
     * Cached per-device loggers.
     *
     * @var array<string, LoggerInterface>
     */
    private static array $fileLoggers = [];

    private const string DEFAULT_DEVICE = 'unknown';

    private const string DEVICE_LOG_DIRECTORY = 'logs/devices';

    private const string DEBUG_DIRECTORY = 'debug';

    private const array ALLOWED_LEVELS = [
        'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency',
    ];

    /**
     * Whether device logging is enabled.
     */
    public static function enabled(): bool
    {
        return (bool) config('logging.device_logs.enabled', true);
    }

    /**
     * Serial configured for targeted per-device file logging.
     */
    public static function debugSn(): ?string
    {
        $sn = trim((string) config('logging.device_logs.debug_sn', ''));

        return $sn !== '' ? $sn : null;
    }

    /**
     * Whether a dedicated file should be written for this SN.
     */
    public static function shouldWritePerFile(string $sn): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if ((bool) config('logging.device_logs.per_file', false)) {
            return true;
        }

        $debugSn = self::debugSn();

        return $debugSn !== null
            && strcasecmp($debugSn, trim($sn)) === 0;
    }

    /**
     * Generic log entry (any allowed level).
     *
     * @param  array<string, mixed>  $context
     */
    public static function log(string $level, string $sn, string $message, array $context = []): void
    {
        self::write($level, $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function debug(string $sn, string $message, array $context = []): void
    {
        self::write('debug', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $sn, string $message, array $context = []): void
    {
        self::write('info', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function notice(string $sn, string $message, array $context = []): void
    {
        self::write('notice', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $sn, string $message, array $context = []): void
    {
        self::write('warning', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $sn, string $message, array $context = []): void
    {
        self::write('error', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function critical(string $sn, string $message, array $context = []): void
    {
        self::write('critical', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function alert(string $sn, string $message, array $context = []): void
    {
        self::write('alert', $sn, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function emergency(string $sn, string $message, array $context = []): void
    {
        self::write('emergency', $sn, $message, $context);
    }

    /**
     * Write to the shared device channel and optionally a per-SN file.
     * Must never throw into the application flow.
     *
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $sn, string $message, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        $level = strtolower(trim($level));
        if (! in_array($level, self::ALLOWED_LEVELS, true)) {
            $level = 'info';
        }

        $sn = trim($sn);

        $context = self::redactContext([
            'sn' => $sn,
            ...$context,
        ]);

        try {
            self::writeToDeviceChannel($level, $message, $context);

            if (self::shouldWritePerFile($sn)) {
                self::writeToDeviceFile($level, $sn, $message, $context);
            }
        } catch (Throwable $e) {
            Log::warning('DeviceLog failed', [
                'sn' => $sn,
                'level' => $level,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function writeToDeviceChannel(string $level, string $message, array $context): void
    {
        Log::channel('device')->log($level, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function writeToDeviceFile(string $level, string $sn, string $message, array $context): void
    {
        self::fileLogger($sn)->log($level, $message, $context);
    }

    private static function fileLogger(string $sn): LoggerInterface
    {
        $safeSn = self::sanitizeSerialNumber($sn);

        if (isset(self::$fileLoggers[$safeSn])) {
            return self::$fileLoggers[$safeSn];
        }

        $directory = self::deviceDirectory($safeSn);
        self::ensureDirectory($directory);

        self::$fileLoggers[$safeSn] = Log::build([
            'driver' => 'daily',
            'path' => $directory . '/device.log',
            'level' => self::logLevel(),
            'days' => self::logDays(),
            'replace_placeholders' => true,
        ]);

        return self::$fileLoggers[$safeSn];
    }

    /**
     * Save a full payload for troubleshooting (OPERLOG, etc.).
     * Gated by logging.device_logs.debug_dump — keep off in production.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function dump(string $sn, string $kind, string $content, array $extra = []): ?string
    {
        if (! self::debugDumpEnabled()) {
            return null;
        }

        $sn = trim($sn);

        try {
            $safeSn = self::sanitizeSerialNumber($sn);

            $directory = self::deviceDirectory($safeSn)
                . DIRECTORY_SEPARATOR
                . self::DEBUG_DIRECTORY;

            self::ensureDirectory($directory);

            $timestamp = Carbon::now();

            $filename = sprintf(
                '%s_%s_%s.txt',
                self::sanitizeFilename($kind),
                $timestamp->format('Y-m-d_His'),
                Str::random(12)
            );

            $path = $directory . DIRECTORY_SEPARATOR . $filename;

            $header = self::buildDumpHeader($sn, $kind, $timestamp, $content, $extra);

            file_put_contents($path, $header . $content, LOCK_EX);

            self::info($sn, 'Debug dump saved', [
                'kind' => $kind,
                'path' => $path,
                'bytes' => strlen($content),
            ]);

            return $path;
        } catch (Throwable $e) {
            Log::warning('DeviceLog dump failed', [
                'sn' => $sn,
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function debugDumpEnabled(): bool
    {
        return (bool) config('logging.device_logs.debug_dump', false);
    }

    private static function logLevel(): string
    {
        return (string) config('logging.device_logs.level', 'info');
    }

    private static function logDays(): int
    {
        return max(1, (int) config('logging.device_logs.days', 14));
    }

    private static function maxContextString(): int
    {
        return max(200, (int) config('logging.device_logs.max_context_string', 2000));
    }

    private static function deviceDirectory(string $safeSn): string
    {
        return storage_path(
            self::DEVICE_LOG_DIRECTORY
            . DIRECTORY_SEPARATOR
            . $safeSn
        );
    }

    /**
     * @throws RuntimeException
     */
    private static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Directory "%s" could not be created.', $directory)
            );
        }
    }

    private static function sanitizeSerialNumber(string $sn): string
    {
        $sn = trim($sn);

        if ($sn === '') {
            return self::DEFAULT_DEVICE;
        }

        // Keep only safe filesystem characters, collapse consecutive underscores
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $sn) ?: self::DEFAULT_DEVICE;
        $safe = preg_replace('/_+/', '_', $safe) ?: self::DEFAULT_DEVICE;

        // Prevent path traversal / overly long names
        return substr($safe, 0, 64);
    }

    private static function sanitizeFilename(string $value): string
    {
        $value = trim($value);
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?: 'dump';

        return substr($safe, 0, 40);
    }

    /**
     * Prevent huge Base64 / bodies from flooding log files.
     * Also redacts common sensitive keys.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function redactContext(array $context): array
    {
        $sensitiveKeys = config('logging.device_logs.sensitive_keys', [
            'password', 'token', 'api_key', 'secret', 'authorization',
        ]);

        $maxLen = self::maxContextString();

        foreach ($context as $key => $value) {
            $keyStr = (string) $key;

            // Redact sensitive keys
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains(strtolower($keyStr), strtolower((string) $sensitive))) {
                    $context[$key] = '********';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $context[$key] = self::redactContext($value);
                continue;
            }

            if (is_string($value) && strlen($value) > $maxLen) {
                $context[$key] = substr($value, 0, 200)
                    . '... [len=' . strlen($value) . ']';
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function buildDumpHeader(
        string $sn,
        string $kind,
        Carbon $timestamp,
        string $content,
        array $extra
    ): string {
        try {
            $extraJson = json_encode(
                $extra,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (JsonException) {
            $extraJson = '{}';
        }

        return implode("\n", [
            "SN: {$sn}",
            "Kind: {$kind}",
            'Time: ' . $timestamp->toDateTimeString(),
            'Bytes: ' . strlen($content),
            'Extra: ' . $extraJson,
            str_repeat('-', 60),
            '',
        ]);
    }
}

```
