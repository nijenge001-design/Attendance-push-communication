<?php

declare(strict_types=1);

namespace App\Services\Imports;

use App\Models\User;
use App\Services\BulkAttendanceLogService;
use App\Services\BulkAttendeeService;
use App\Support\BulkResult;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class SpreadsheetImportService
{
    public function __construct(
        private SpreadsheetReader $reader,
        private SpreadsheetWriter $writer,
        private ColumnMapper $mapper,
        private BulkAttendeeService $attendees,
        private BulkAttendanceLogService $logs,
    ) {}

    /**
     * @param  array{mode?: string, dryRun?: bool, siteIds?: list<string>, deviceSerials?: list<string>}  $options
     */
    public function importAttendees(User $user, UploadedFile $file, array $options = []): BulkResult
    {
        $max = (int) config('api.import_max_rows', 2000);
        $parsed = $this->reader->read($file->getRealPath() ?: $file->getPathname(), $file->getClientOriginalName());
        $map = $this->mapper->map($parsed['headers'], ColumnMapper::ATTENDEE_ALIASES);

        if (! isset($map['pin'])) {
            throw new InvalidArgumentException('The spreadsheet must include a PIN column (pin, employee_id, user_id, …).');
        }

        $items = [];
        $result = new BulkResult('attendees-import');
        $seenPins = [];

        foreach ($parsed['rows'] as $index => $row) {
            if (count($items) + $result->failed >= $max) {
                $result->itemError($index, "Row limit of {$max} exceeded. Split the file and retry.", 'Too Many Rows');
                continue;
            }

            $raw = $this->mapper->rowToItem($row, $map);
            $pin = trim((string) ($raw['pin'] ?? ''));
            if ($pin === '') {
                $result->itemError($index, 'PIN is required.', meta: ['row' => $index + 2]);
                continue;
            }
            if (isset($seenPins[$pin])) {
                $result->itemError($index, 'Duplicate PIN in the spreadsheet.', meta: ['pin' => $pin, 'row' => $index + 2]);
                continue;
            }
            $seenPins[$pin] = true;

            $item = [
                'pin' => $pin,
                'name' => $this->nullable($raw['name'] ?? null),
                'privilege' => $this->intOrNull($raw['privilege'] ?? null) ?? 0,
                'password' => $this->nullable($raw['password'] ?? null),
                'cardNumber' => $this->nullable($raw['cardNumber'] ?? null),
                'viceCard' => $this->nullable($raw['viceCard'] ?? null),
                'groupId' => $this->intOrNull($raw['groupId'] ?? null) ?? 1,
                'timezone' => $this->nullable($raw['timezone'] ?? null),
                'verificationMode' => $this->intOrNull($raw['verificationMode'] ?? null) ?? -1,
                'siteIds' => $this->mapper->explodeList($raw['siteIds'] ?? '') ?: ($options['siteIds'] ?? []),
                'deviceSerials' => $this->mapper->explodeList($raw['deviceSerials'] ?? '') ?: ($options['deviceSerials'] ?? []),
            ];

            $items[] = ['index' => $index, 'item' => $item];
        }

        if (($options['dryRun'] ?? false) === true) {
            return $this->previewAttendees($items, $result);
        }

        $chunkSize = (int) config('api.bulk_max_items', 500);
        $buffer = [];
        $bufferStart = 0;

        foreach ($items as $i => $entry) {
            if ($buffer === []) {
                $bufferStart = $entry['index'];
            }
            $buffer[] = $entry['item'];
            $isLast = $i === array_key_last($items);
            if (count($buffer) === $chunkSize || $isLast) {
                $chunkResult = $this->attendees->upsert(
                    $user,
                    $buffer,
                    $options['mode'] ?? 'upsert',
                );
                $result->merge($chunkResult, $bufferStart);
                $buffer = [];
            }
        }

        return $result;
    }

    /**
     * @param  array{dryRun?: bool, dispatchJobs?: bool, broadcast?: bool, deviceSerial?: ?string}  $options
     */
    public function importAttendanceLogs(User $user, UploadedFile $file, array $options = []): BulkResult
    {
        $max = (int) config('api.import_max_rows', 2000);
        $parsed = $this->reader->read($file->getRealPath() ?: $file->getPathname(), $file->getClientOriginalName());
        $map = $this->mapper->map($parsed['headers'], ColumnMapper::ATTENDANCE_ALIASES);

        if (! isset($map['pin'], $map['timestamp'], $map['status'], $map['verifyMode'])) {
            throw new InvalidArgumentException('The spreadsheet must include pin, timestamp, status, and verifyMode columns.');
        }

        $forcedSerial = $options['deviceSerial'] ?? null;
        if (! isset($map['deviceSerial']) && ! is_string($forcedSerial)) {
            throw new InvalidArgumentException('Provide a deviceSerial column or pass deviceSerial in the form.');
        }

        $result = new BulkResult('attendance-logs-import');
        $items = [];

        foreach ($parsed['rows'] as $index => $row) {
            if (count($items) + $result->failed >= $max) {
                $result->itemError($index, "Row limit of {$max} exceeded. Split the file and retry.", 'Too Many Rows');
                continue;
            }

            $raw = $this->mapper->rowToItem($row, $map);
            $pin = trim((string) ($raw['pin'] ?? ''));
            $timestamp = $this->excelTimestamp($raw['timestamp'] ?? '');
            $serial = trim((string) ($raw['deviceSerial'] ?? $forcedSerial ?? ''));

            if ($pin === '' || $timestamp === null || $serial === '') {
                $result->itemError($index, 'pin, timestamp, and deviceSerial are required.', meta: ['row' => $index + 2]);
                continue;
            }

            $items[] = [
                'index' => $index,
                'item' => [
                    'pin' => $pin,
                    'timestamp' => $timestamp,
                    'status' => $this->intOrNull($raw['status'] ?? null) ?? 0,
                    'verifyMode' => $this->intOrNull($raw['verifyMode'] ?? null) ?? 1,
                    'deviceSerial' => $serial,
                    'workcode' => $this->nullable($raw['workcode'] ?? null),
                    'type' => $this->intOrNull($raw['type'] ?? null) ?? 0,
                    'maskFlag' => $this->intOrNull($raw['maskFlag'] ?? null),
                    'temperature' => is_numeric($raw['temperature'] ?? null) ? (float) $raw['temperature'] : null,
                    'convTemperature' => is_numeric($raw['convTemperature'] ?? null) ? (float) $raw['convTemperature'] : null,
                    'idNumber' => $this->nullable($raw['idNumber'] ?? null),
                ],
            ];
        }

        if (($options['dryRun'] ?? false) === true) {
            $result->created = count($items);
            $result->operation = 'attendance-logs-import-dry-run';

            return $result;
        }

        $chunkSize = (int) config('api.bulk_max_items', 500);
        $buffer = [];
        $bufferStart = 0;

        foreach ($items as $i => $entry) {
            if ($buffer === []) {
                $bufferStart = $entry['index'];
            }
            $buffer[] = $entry['item'];
            $isLast = $i === array_key_last($items);
            if (count($buffer) === $chunkSize || $isLast) {
                $chunkResult = $this->logs->store(
                    $user,
                    $buffer,
                    null,
                    (bool) ($options['dispatchJobs'] ?? true),
                    (bool) ($options['broadcast'] ?? false),
                );
                $result->merge($chunkResult, $bufferStart);
                $buffer = [];
            }
        }

        return $result;
    }

    /**
     * @return array{headers: list<string>, example: list<string>}
     */
    public function attendeeTemplateColumns(): array
    {
        return [
            'headers' => ['pin', 'name', 'privilege', 'password', 'cardNumber', 'viceCard', 'groupId', 'timezone', 'verificationMode', 'siteIds', 'deviceSerials'],
            'example' => ['1001', 'Jane Doe', '0', '', 'AABB11', '', '1', '', '-1', '', 'PSS7234900035'],
        ];
    }

    /**
     * @return array{headers: list<string>, example: list<string>}
     */
    public function attendanceTemplateColumns(): array
    {
        return [
            'headers' => ['pin', 'timestamp', 'status', 'verifyMode', 'deviceSerial', 'workcode', 'type', 'maskFlag', 'temperature'],
            'example' => ['1001', '2026-09-12 08:01:00', '0', '1', 'PSS7234900035', '', '0', '', ''],
        ];
    }

    public function downloadTemplate(string $kind, string $format): array
    {
        $spec = $kind === 'attendance'
            ? $this->attendanceTemplateColumns()
            : $this->attendeeTemplateColumns();

        $name = $kind === 'attendance' ? 'attendance-logs-import' : 'attendees-import';

        if ($format === 'csv') {
            return [
                'filename' => $name.'.csv',
                'mime' => 'text/csv',
                'body' => $this->writer->csv($spec['headers'], [$spec['example']]),
            ];
        }

        return [
            'filename' => $name.'.xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'body' => $this->writer->xlsx($spec['headers'], [$spec['example']]),
        ];
    }

    /**
     * @param  list<array{index: int, item: array<string, mixed>}>  $items
     */
    private function previewAttendees(array $items, BulkResult $result): BulkResult
    {
        $result->operation = 'attendees-import-dry-run';
        $pins = array_map(fn ($e) => $e['item']['pin'], $items);
        $existing = $pins === []
            ? []
            : \App\Models\Attendee::query()->whereIn('pin', $pins)->pluck('pin')->all();
        $existing = array_flip($existing);

        foreach ($items as $entry) {
            if (isset($existing[$entry['item']['pin']])) {
                $result->updated++;
            } else {
                $result->created++;
            }
        }

        return $result;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function excelTimestamp(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial > 20000 && $serial < 90000) {
                $unix = (int) round(($serial - 25569) * 86400);

                return Carbon::createFromTimestampUTC($unix)->format('Y-m-d H:i:s');
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
