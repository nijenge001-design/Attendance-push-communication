<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessBulkAttendanceChunk;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\User;
use App\Support\BulkResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BulkAttendanceLogService
{
    /**
     * Insert punches, skipping rows that already exist on
     * (device_serial, pin, timestamp).
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function store(User $user, array $items, ?Device $forcedDevice = null, bool $dispatchJobs = true, bool $broadcast = false): BulkResult
    {
        $result = new BulkResult('attendance-logs-bulk');
        $chunk = (int) config('api.bulk_insert_chunk', 100);

        $prepared = [];
        foreach ($items as $index => $item) {
            $serial = $forcedDevice?->serial_number ?? ($item['deviceSerial'] ?? null);
            if (! is_string($serial) || $serial === '') {
                $result->itemError($index, 'deviceSerial is required.', 'Validation Error');
                continue;
            }

            if ($forcedDevice === null) {
                $device = Device::query()->where('serial_number', $serial)->first();
                if (! $device || ! $user->hasAccessToDevice($device)) {
                    $result->itemError($index, 'No access to device.', 'Forbidden', ['deviceSerial' => $serial]);
                    continue;
                }
            }

            $timestamp = Carbon::parse($item['timestamp']);
            $key = $serial.'|'.$item['pin'].'|'.$timestamp->format('Y-m-d H:i:s');

            if (isset($prepared[$key])) {
                $result->skipped++;
                continue;
            }

            $id = (string) Str::uuid();
            $now = now();

            $prepared[$key] = [
                'index' => $index,
                'row' => [
                    'id' => $id,
                    'pin' => (string) $item['pin'],
                    'device_serial' => $serial,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => (int) $item['status'],
                    'verify_mode' => (int) $item['verifyMode'],
                    'workcode' => $item['workcode'] ?? null,
                    'type' => (int) ($item['type'] ?? 0),
                    'mask_flag' => $item['maskFlag'] ?? null,
                    'temperature' => $item['temperature'] ?? null,
                    'conv_temperature' => $item['convTemperature'] ?? null,
                    'id_number' => $item['idNumber'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];
        }

        if ($prepared === []) {
            return $result;
        }

        $existingKeys = $this->existingKeys(array_map(fn ($p) => $p['row'], $prepared));

        $toInsert = [];
        $newIds = [];

        foreach ($prepared as $key => $payload) {
            if (isset($existingKeys[$key])) {
                $result->skipped++;
                continue;
            }

            $toInsert[] = $payload['row'];
            $newIds[] = $payload['row']['id'];
        }

        foreach (array_chunk($toInsert, $chunk) as $batch) {
            AttendanceLog::query()->insert($batch);
        }

        $result->created = count($toInsert);
        $result->ids = $newIds;

        if ($dispatchJobs && $newIds !== []) {
            foreach (array_chunk($newIds, $chunk) as $idChunk) {
                ProcessBulkAttendanceChunk::dispatch($idChunk, $broadcast);
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function existingKeys(array $rows): array
    {
        $found = [];

        foreach (array_chunk($rows, 50) as $chunk) {
            AttendanceLog::query()
                ->where(function ($q) use ($chunk) {
                    foreach ($chunk as $row) {
                        $q->orWhere(function ($inner) use ($row) {
                            $inner->where('device_serial', $row['device_serial'])
                                ->where('pin', $row['pin'])
                                ->where('timestamp', $row['timestamp']);
                        });
                    }
                })
                ->get(['device_serial', 'pin', 'timestamp'])
                ->each(function (AttendanceLog $log) use (&$found) {
                    $key = $log->device_serial.'|'.$log->pin.'|'.$log->timestamp?->format('Y-m-d H:i:s');
                    $found[$key] = true;
                });
        }

        return $found;
    }
}
