```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AttendancePunched;
use App\Jobs\ProcessAttendanceLog;
use App\Jobs\ProcessBioTemplate;
use App\Jobs\ProcessOperLogJob;
use App\Jobs\StoreBioPhotoJob;
use App\Models\AttendanceLog;
use App\Models\Attendee;
use App\Models\AttendeeImportError;
use App\Models\BioTemplate;
use App\Models\Photo;
use App\Support\DeviceLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeviceDataHandler
{
    // =====================================================================
    // ATTLOG
    // =====================================================================

    /**
     * Protocol format (2024):
     * Pin HT Time HT Status HT Verify HT Workcode HT Reserved1 HT Reserved2
     *     [HT MaskFlag HT Temperature HT ConvTemperature]
     * ID-card variant may also carry IDNum + Type.
     */
    public function handleAttendanceLogs(string $sn, string $content): string
    {
        $this->saveDebugFile($sn, 'attlog', $content);

        $count = 0;
        $errors = 0;

        foreach ($this->iterateLines($content) as $line) {
            $fields = explode("\t", $line);

            // Minimum viable record: Pin + Time + Status + Verify
            if (count($fields) < 4) {
                $errors++;
                continue;
            }

            $pin = trim($fields[0] ?? '');
            $timeRaw = trim($fields[1] ?? '');

            if ($pin === '' || $timeRaw === '') {
                $errors++;
                continue;
            }

            try {
                $timestamp = $this->parseDeviceTimestamp($timeRaw);
            } catch (Throwable) {
                DeviceLog::warning($sn, 'ATTLOG invalid timestamp', [
                    'raw' => $timeRaw,
                    'line' => $line,
                ]);
                $errors++;
                continue;
            }

            $attributes = [
                'device_serial' => $sn,
                'pin' => $pin,
                'timestamp' => $timestamp,
            ];

            $values = [
                'status' => (int) ($fields[2] ?? 0),
                'verify_mode' => (int) ($fields[3] ?? 0),
                'workcode' => $this->nullIfEmpty($fields[4] ?? null),
                'reserved1' => $this->nullIfEmpty($fields[5] ?? null),
                'reserved2' => $this->nullIfEmpty($fields[6] ?? null),
                // Index 7/8 can be either (MaskFlag/Temp) or (IDNum/Type) depending on firmware
                // We store both interpretations safely.
                'id_number' => $this->nullIfEmpty($fields[7] ?? null),
                'type' => isset($fields[8]) && is_numeric($fields[8]) ? (int) $fields[8] : 0,
                'mask_flag' => isset($fields[7]) && in_array($fields[7], ['0', '1'], true)
                    ? (int) $fields[7]
                    : (isset($fields[8]) && in_array($fields[8], ['0', '1'], true) ? (int) $fields[8] : null),
                'temperature' => $this->parseTemperature($fields[8] ?? $fields[9] ?? null),
                'conv_temperature' => $this->parseTemperature($fields[9] ?? $fields[10] ?? null),
            ];

            try {
                $log = AttendanceLog::updateOrCreate($attributes, $values);

                ProcessAttendanceLog::dispatch($log);
                event(new AttendancePunched($log));
                $count++;
            } catch (Throwable $e) {
                DeviceLog::error($sn, 'ATTLOG save failed', [
                    'pin' => $pin,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        Log::channel('attendance')->info('ATTLOG batch', [
            'sn' => $sn,
            'count' => $count,
            'errors' => $errors,
        ]);

        DeviceLog::info($sn, 'ATTLOG processed', [
            'count' => $count,
            'errors' => $errors,
        ]);

        return "OK: {$count}";
    }

    // =====================================================================
    // OPERLOG – queue only, process in background
    // =====================================================================

    public function handleOperationLogs(string $sn, string $content): string
    {
        // Normalize encoding (devices sometimes send mixed/legacy encodings)
        $content = $this->normalizeEncoding($content);

        $path = sprintf('operlog_jobs/%s/%s.txt', $sn, now()->format('Y-m-d_His_u'));
        Storage::disk('local')->put($path, $content);

        Log::channel('user_info')->info('OPERLOG queued', [
            'sn' => $sn,
            'bytes' => strlen($content),
            'path' => $path,
        ]);

        DeviceLog::info($sn, 'OPERLOG queued', [
            'bytes' => strlen($content),
            'path' => $path,
        ]);

        ProcessOperLogJob::dispatch($sn, $path);

        return 'OK';
    }

    /**
     * Called from ProcessOperLogJob.
     */
    public function processOperLogContent(string $sn, string $content): string
    {
        $userCount = $fpCount = $faceCount = $bioCount = $photoCount = $other = 0;

        foreach ($this->iterateLines($content) as $line) {
            if (str_starts_with($line, 'USER')) {
                $this->parseAndStoreUser($sn, $line);
                $userCount++;
                continue;
            }

            if (str_starts_with($line, 'FP ') || str_starts_with($line, 'FINGERTMP')) {
                $this->parseAndStoreFingerprint($sn, $line);
                $fpCount++;
                continue;
            }

            if (str_starts_with($line, 'FACE')) {
                $this->parseAndStoreFace($sn, $line);
                $faceCount++;
                continue;
            }

            if (str_starts_with($line, 'BIODATA')) {
                $this->parseAndStoreBioData($sn, $line);
                $bioCount++;
                continue;
            }

            if (str_starts_with($line, 'BIOPHOTO')) {
                $this->parseAndStoreBioPhoto($sn, $line);
                $photoCount++;
                continue;
            }

            $other++;
        }

        Log::channel('user_info')->info('OPERLOG processed', [
            'sn' => $sn,
            'users' => $userCount,
            'fps' => $fpCount,
            'faces' => $faceCount,
            'bio' => $bioCount,
            'photos' => $photoCount,
            'other' => $other,
        ]);

        DeviceLog::info($sn, 'OPERLOG processed', [
            'users' => $userCount,
            'fps' => $fpCount,
            'faces' => $faceCount,
            'bio' => $bioCount,
            'photos' => $photoCount,
            'other' => $other,
        ]);

        return 'OK: ' . ($userCount + $fpCount + $faceCount + $bioCount + $photoCount + $other);
    }

    // =====================================================================
    // BIODATA table
    // =====================================================================

    public function handleBioData(string $sn, string $content): string
    {
        $this->saveDebugFile($sn, 'biodata', $content);

        $count = 0;

        foreach ($this->iterateLines($content) as $line) {
            if (! str_starts_with($line, 'BIODATA')) {
                continue;
            }
            $this->parseAndStoreBioData($sn, $line);
            $count++;
        }

        return "OK: {$count}";
    }

    // =====================================================================
    // ATTPHOTO
    // =====================================================================

    public function handleAttendancePhoto(string $sn, string $content): string
    {
        $parts = explode("\0", $content, 2);
        $header = $parts[0] ?? '';
        $binary = $parts[1] ?? '';

        $params = $this->parseKeyValueLines($header);
        $filename = $params['PIN'] ?? $params['FileName'] ?? ('att_' . now()->format('YmdHis') . '.jpg');
        $pin = pathinfo($filename, PATHINFO_FILENAME);

        // Prefer storing binary on disk; avoid bloating the DB with base64
        $path = "photos/{$sn}/att/{$filename}";
        if ($binary !== '') {
            Storage::disk('local')->put($path, $binary);
        }

        Photo::create([
            'device_serial' => $sn,
            'pin' => $pin,
            'filename' => $filename,
            'type' => Photo::TYPE_ATTENDANCE,
            'content' => null,
            'url' => $path,
        ]);

        DeviceLog::info($sn, 'ATTPHOTO saved', [
            'pin' => $pin,
            'filename' => $filename,
            'bytes' => strlen($binary),
        ]);

        return 'OK';
    }

    // =====================================================================
    // USERINFO table
    // =====================================================================

    public function handleUserInfo(string $sn, string $content): string
    {
        $this->saveDebugFile($sn, 'userinfo', $content);

        $count = 0;

        foreach ($this->iterateLines($content) as $line) {
            if (! str_starts_with($line, 'USER')) {
                continue;
            }
            $this->parseAndStoreUser($sn, $line);
            $count++;
        }

        Log::channel('user_info')->info('USERINFO table processed', [
            'sn' => $sn,
            'count' => $count,
        ]);

        return "OK: {$count}";
    }

    // =====================================================================
    // IDCARD
    // =====================================================================

    public function handleIdCard(string $sn, string $content): string
    {
        $count = 0;

        foreach ($this->iterateLines($content) as $line) {
            if (! str_starts_with($line, 'IDCARD')) {
                continue;
            }
            $data = $this->parseKeyValueLine(substr($line, 7));
            DeviceLog::debug($sn, 'IDCARD received', ['data' => $data]);
            $count++;
        }

        return "OK: {$count}";
    }

    // =====================================================================
    // ERRORLOG
    // =====================================================================

    public function handleErrorLog(string $sn, string $content): string
    {
        $count = 0;

        foreach ($this->iterateLines($content) as $line) {
            if (! str_starts_with($line, 'ERRORLOG')) {
                continue;
            }
            $data = $this->parseKeyValueLine(substr($line, 9));
            DeviceLog::warning($sn, 'Device ERRORLOG', ['data' => $data]);
            $count++;
        }

        return "OK: {$count}";
    }

    // =====================================================================
    // Parsers
    // =====================================================================

    private function parseAndStoreUser(string $sn, string $line): void
    {
        if (str_starts_with($line, 'USER')) {
            $line = trim(substr($line, 4));
        }

        $data = $this->parseKeyValueLine($line);
        if (empty($data['PIN'])) {
            return;
        }

        $payload = [
            'pin' => (string) $data['PIN'],
            'name' => $data['Name'] ?? '',
            'privilege' => (int) ($data['Pri'] ?? 0),
            'password' => $this->nullIfEmpty($data['Passwd'] ?? null),
            'card_number' => $this->nullIfEmptyCard($data['Card'] ?? null),
            'vice_card' => $this->nullIfEmptyCard($data['ViceCard'] ?? null),
            'group_id' => (int) ($data['Grp'] ?? 1),
            'timezone' => $this->nullIfEmpty($data['TZ'] ?? null),
            'verification_mode' => (int) ($data['Verify'] ?? -1),
        ];

        try {
            $attendee = Attendee::updateOrCreate(
                ['pin' => $payload['pin']],
                $payload
            );

            $attendee->devices()->syncWithoutDetaching([
                $sn => [
                    'privilege' => $payload['privilege'],
                    'group_id' => $payload['group_id'],
                    'timezone' => $payload['timezone'],
                    'active' => true,
                ],
            ]);

            Log::channel('user_info')->debug('User saved', [
                'pin' => $payload['pin'],
                'name' => $payload['name'],
                'sn' => $sn,
            ]);
        } catch (Throwable $e) {
            Log::channel('user_info')->error('User save failed', [
                'pin' => $payload['pin'],
                'sn' => $sn,
                'error' => $e->getMessage(),
            ]);

            DeviceLog::error($sn, 'User save failed', [
                'pin' => $payload['pin'],
                'error' => $e->getMessage(),
            ]);

            $errorCode = str_contains($e->getMessage(), 'card_number')
                ? 'duplicate_card'
                : 'import_failed';

            AttendeeImportError::create([
                'device_serial' => $sn,
                'pin' => $payload['pin'],
                'name' => $payload['name'],
                'card_number' => $payload['card_number'],
                'vice_card' => $payload['vice_card'],
                'privilege' => $payload['privilege'],
                'group_id' => $payload['group_id'],
                'timezone' => $payload['timezone'],
                'verification_mode' => $payload['verification_mode'],
                'password' => $payload['password'],
                'error_code' => $errorCode,
                'error_message' => $e->getMessage(),
                'raw_data' => $data,
                'status' => AttendeeImportError::STATUS_PENDING,
            ]);
        }
    }

    private function parseAndStoreFingerprint(string $sn, string $line): void
    {
        if (str_starts_with($line, 'FP ')) {
            $line = trim(substr($line, 3));
        } elseif (str_starts_with($line, 'FINGERTMP')) {
            $line = trim(substr($line, 9));
        }

        $data = $this->parseKeyValueLine($line);
        $pin = $data['PIN'] ?? $data['Pin'] ?? null;
        if (! $pin) {
            return;
        }

        $template = BioTemplate::updateOrCreate(
            [
                'device_serial' => $sn,
                'pin' => $pin,
                'type' => BioTemplate::TYPE_FINGER,
                'no' => (int) ($data['FID'] ?? $data['No'] ?? 0),
                'index' => (int) ($data['Index'] ?? 0),
            ],
            [
                'valid' => (int) ($data['Valid'] ?? 1),
                'duress' => (int) ($data['Duress'] ?? 0),
                'template_data' => $data['TMP'] ?? $data['Tmp'] ?? '',
                'format' => $data['Format'] ?? null,
                'major_ver' => $data['MajorVer'] ?? null,
                'minor_ver' => $data['MinorVer'] ?? null,
            ]
        );

        ProcessBioTemplate::dispatch($template);
    }

    private function parseAndStoreFace(string $sn, string $line): void
    {
        if (str_starts_with($line, 'FACE')) {
            $line = trim(substr($line, 4));
        }

        $data = $this->parseKeyValueLine($line);
        $pin = $data['PIN'] ?? $data['Pin'] ?? null;
        if (! $pin) {
            return;
        }

        $template = BioTemplate::updateOrCreate(
            [
                'device_serial' => $sn,
                'pin' => $pin,
                'type' => BioTemplate::TYPE_FACE,
                'no' => (int) ($data['FID'] ?? $data['No'] ?? 0),
                'index' => (int) ($data['Index'] ?? 0),
            ],
            [
                'valid' => (int) ($data['Valid'] ?? 1),
                'duress' => (int) ($data['Duress'] ?? 0),
                'template_data' => $data['TMP'] ?? $data['Tmp'] ?? '',
                'format' => $data['Format'] ?? null,
                'major_ver' => $data['MajorVer'] ?? null,
                'minor_ver' => $data['MinorVer'] ?? null,
            ]
        );

        ProcessBioTemplate::dispatch($template);
    }

    private function parseAndStoreBioData(string $sn, string $line): void
    {
        if (str_starts_with($line, 'BIODATA')) {
            $line = trim(substr($line, 7));
        }

        $data = $this->parseKeyValueLine($line);
        $pin = $data['Pin'] ?? $data['PIN'] ?? null;
        if (! $pin) {
            return;
        }

        $template = BioTemplate::updateOrCreate(
            [
                'device_serial' => $sn,
                'pin' => $pin,
                'type' => (int) ($data['Type'] ?? 0),
                'no' => (int) ($data['No'] ?? 0),
                'index' => (int) ($data['Index'] ?? 0),
            ],
            [
                'valid' => (int) ($data['Valid'] ?? 1),
                'duress' => (int) ($data['Duress'] ?? 0),
                'major_ver' => $data['MajorVer'] ?? null,
                'minor_ver' => $data['MinorVer'] ?? null,
                'format' => $data['Format'] ?? null,
                'template_data' => $data['Tmp'] ?? $data['TMP'] ?? '',
            ]
        );

        ProcessBioTemplate::dispatch($template);
    }

    private function parseAndStoreBioPhoto(string $sn, string $line): void
    {
        if (str_starts_with($line, 'BIOPHOTO')) {
            $line = trim(substr($line, 8));
        }

        $data = $this->parseKeyValueLine($line);
        $pin = $data['PIN'] ?? $data['Pin'] ?? null;
        if (! $pin) {
            return;
        }

        $filename = $data['FileName'] ?? ($pin . '.jpg');
        $type = (int) ($data['Type'] ?? Photo::TYPE_VISIBLE_FACE);
        $base64 = $data['Content'] ?? '';

        StoreBioPhotoJob::dispatch($sn, $pin, $filename, $type, $base64);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function iterateLines(string $content): \Generator
    {
        foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
            $line = trim($line);
            if ($line !== '') {
                yield $line;
            }
        }
    }

    private function parseKeyValueLine(string $line): array
    {
        $data = [];
        foreach (explode("\t", $line) as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $data[trim($key)] = trim($value);
        }

        return $data;
    }

    private function parseKeyValueLines(string $text): array
    {
        $data = [];

        foreach ($this->iterateLines($text) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value);
        }

        return $data;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Treat common ZKTeco "no card" placeholders as null.
     */
    private function nullIfEmptyCard(mixed $value): ?string
    {
        $v = $this->nullIfEmpty($value);
        if ($v === null) {
            return null;
        }
        if (in_array($v, ['0', '65535', '65536'], true)) {
            return null;
        }

        return $v;
    }

    private function parseTemperature(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $f = (float) $value;

        // Sanity range for human body temperature readings
        return ($f > 20 && $f < 50) ? round($f, 2) : null;
    }

    /**
     * Parse device timestamp strings (YYYY-MM-DD HH:MM:SS or slight variants).
     */
    private function parseDeviceTimestamp(string $raw): \Carbon\Carbon
    {
        $raw = trim($raw);
        // Normalise common malformed spaces around colons
        $raw = preg_replace('/\s*:\s*/', ':', $raw) ?? $raw;
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;

        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $raw)
            ?? \Carbon\Carbon::parse($raw);
    }

    private function normalizeEncoding(string $content): string
    {
        // Force UTF-8 and drop invalid sequences
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, GBK, GB2312, ISO-8859-1');
        }

        return iconv('UTF-8', 'UTF-8//IGNORE', $content) ?: $content;
    }

    private function saveDebugFile(string $sn, string $table, string $content): void
    {
        if (! config('app.debug') && ! config('logging.device_logs.debug_dump', false)) {
            return;
        }

        // Prefer the dedicated DeviceLog dump helper when available
        if (class_exists(DeviceLog::class)) {
            DeviceLog::dump($sn, $table, $content, [
                'lines' => substr_count($content, "\n") + 1,
            ]);

            return;
        }

        $path = sprintf(
            'debug/%s/%s/%s.txt',
            $table,
            $sn,
            now()->format('Y-m-d_His_u')
        );

        $header = implode("\n", [
            "SN: {$sn}",
            "Table: {$table}",
            'Time: ' . now()->toDateTimeString(),
            'Bytes: ' . strlen($content),
            'Lines: ' . (substr_count($content, "\n") + 1),
            str_repeat('-', 60),
            '',
        ]);

        Storage::disk('local')->put($path, $header . $content);
    }
}
```
