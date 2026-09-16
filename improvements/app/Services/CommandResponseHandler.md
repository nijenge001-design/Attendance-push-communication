```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CommandStatusUpdated;
use App\Jobs\DeviceInfoUpdated;
use App\Models\Attendee;
use App\Models\PendingCommand;
use App\Support\DeviceLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommandResponseHandler
{
    public function saveResponse(string $commandId, array $bodyLines): void
    {
        $cmd = PendingCommand::where('command_id', $commandId)->first();

        if (! $cmd) {
            Log::warning("Command not found for ID: {$commandId}");

            return;
        }

        $firstLine = $bodyLines[0] ?? '';
        $returnCode = 0;
        $remaining = [];

        if (str_starts_with($firstLine, 'ID=')) {
            parse_str($firstLine, $params);
            $returnCode = (int) ($params['Return'] ?? 0);
            $remaining = array_slice($bodyLines, 1);
        } else {
            $remaining = $bodyLines;
        }

        $resultData = [
            'return_code' => $returnCode,
            'body' => implode("\n", $remaining),
            'raw_lines' => $bodyLines,
        ];

        // Failed → retry
        if ($returnCode !== 0) {
            Log::warning("Command {$commandId} failed", [
                'return_code' => $returnCode,
                'retry_count' => $cmd->retry_count,
                'max_retries' => $cmd->max_retries,
                'body' => $resultData['body'],
            ]);

            DeviceLog::warning($cmd->device_serial, 'Command failed', [
                'command_id' => $commandId,
                'return_code' => $returnCode,
                'retry_count' => $cmd->retry_count,
            ]);

            $cmd->scheduleRetry(5);
            event(new CommandStatusUpdated($cmd->fresh()));

            return;
        }

        // Success
        if ($cmd->is_recurring && $cmd->recurrence) {
            $cmd->update([
                'result' => $resultData,
                'return_code' => $returnCode,
                'executed_at' => now(),
                'sent_at' => null,
                'next_run_at' => $cmd->calculateNextRun(),
                'executed' => false,
                'retry_count' => 0,
                'available_at' => null,
            ]);
        } else {
            $cmd->update([
                'executed' => true,
                'result' => $resultData,
                'return_code' => $returnCode,
                'executed_at' => now(),
                'retry_count' => 0,
                'available_at' => null,
            ]);
        }

        $cmd->refresh();
        $this->process($cmd);
        event(new CommandStatusUpdated($cmd));
    }

    private function process(PendingCommand $cmd): void
    {
        $text = $cmd->command_text ?? '';

        try {
            if (str_contains($text, 'INFO')) {
                $this->handleInfoResponse($cmd);
            }

            if (str_contains($text, 'DATA QUERY USERINFO') || str_contains($text, 'QUERY USERINFO')) {
                $this->handleUserInfoQueryResponse($cmd);
            }
        } catch (Throwable $e) {
            Log::error('CommandResponseHandler process failed', [
                'command_id' => $cmd->command_id,
                'error' => $e->getMessage(),
            ]);
            DeviceLog::error($cmd->device_serial, 'Response processing failed', [
                'command_id' => $cmd->command_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleUserInfoQueryResponse(PendingCommand $cmd): void
    {
        $body = trim($cmd->result['body'] ?? '');

        Log::info('USERINFO query raw body', [
            'cmd' => $cmd->command_id,
            'sn' => $cmd->device_serial,
            'body_len' => strlen($body),
        ]);

        if ($body === '') {
            Log::info('USERINFO query returned empty body', ['cmd' => $cmd->command_id]);

            return;
        }

        $sn = $cmd->device_serial;
        $count = 0;

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'USER')) {
                $line = trim(substr($line, 4));
            }

            $fields = $this->parseKeyValueLine($line);
            if (empty($fields['PIN'])) {
                continue;
            }

            $attendee = Attendee::updateOrCreate(
                ['pin' => $fields['PIN']],
                [
                    'name' => $fields['Name'] ?? '',
                    'privilege' => (int) ($fields['Pri'] ?? 0),
                    'password' => $fields['Passwd'] ?? null,
                    'card_number' => $this->nullIfEmptyCard($fields['Card'] ?? null),
                    'vice_card' => $this->nullIfEmptyCard($fields['ViceCard'] ?? null),
                    'group_id' => (int) ($fields['Grp'] ?? 1),
                    'timezone' => $fields['TZ'] ?? null,
                    'verification_mode' => (int) ($fields['Verify'] ?? -1),
                ]
            );

            $attendee->devices()->syncWithoutDetaching([
                $sn => [
                    'privilege' => (int) ($fields['Pri'] ?? 0),
                    'group_id' => (int) ($fields['Grp'] ?? 1),
                    'timezone' => $fields['TZ'] ?? null,
                    'active' => true,
                ],
            ]);

            $count++;
        }

        Log::info("USERINFO query imported {$count} user(s)", [
            'serial' => $sn,
            'cmd' => $cmd->command_id,
        ]);

        DeviceLog::info($sn, 'USERINFO query imported', [
            'count' => $count,
            'command_id' => $cmd->command_id,
        ]);
    }

    private function handleInfoResponse(PendingCommand $cmd): void
    {
        $body = $cmd->result['body'] ?? '';
        $info = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $info[trim($key)] = trim($value);
        }

        $device = $cmd->device;
        if (! $device) {
            return;
        }

        $device->device_name = $info['~DeviceName'] ?? $device->device_name;
        $device->mac_address = $info['MAC'] ?? $device->mac_address;
        $device->firmware_version = $info['FWVersion'] ?? $device->firmware_version;
        $device->push_version = $info['PushVersion'] ?? $device->push_version;
        $device->platform = $info['~Platform'] ?? $device->platform;
        $device->ip = $info['IPAddress'] ?? $device->ip;

        $device->user_count = isset($info['UserCount']) ? (int) $info['UserCount'] : $device->user_count;
        $device->fp_count = isset($info['FPCount']) ? (int) $info['FPCount'] : $device->fp_count;
        $device->face_count = isset($info['FaceCount']) ? (int) $info['FaceCount'] : $device->face_count;
        $device->attlog_count = isset($info['TransactionCount']) ? (int) $info['TransactionCount'] : $device->attlog_count;

        $device->finger_fun_on = isset($info['FingerFunOn']) ? (bool) $info['FingerFunOn'] : $device->finger_fun_on;
        $device->face_fun_on = isset($info['FaceFunOn']) ? (bool) $info['FaceFunOn'] : $device->face_fun_on;
        $device->photo_fun_on = isset($info['PhotoFunOn']) ? (bool) $info['PhotoFunOn'] : $device->photo_fun_on;

        $device->capabilities = array_merge($device->capabilities ?? [], $info);
        $device->save();

        DeviceInfoUpdated::dispatch($device);

        Log::info('Device INFO updated', [
            'serial' => $device->serial_number,
            'name' => $device->device_name,
            'users' => $device->user_count,
        ]);

        DeviceLog::info($device->serial_number, 'Device INFO updated', [
            'name' => $device->device_name,
            'users' => $device->user_count,
            'fp' => $device->fp_count,
            'face' => $device->face_count,
        ]);
    }

    private function parseKeyValueLine(string $line): array
    {
        $data = [];
        foreach (explode("\t", $line) as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $data[trim($key)] = $value;
        }

        return $data;
    }

    private function nullIfEmptyCard(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (in_array($value, ['0', '65535', '65536'], true)) {
            return null;
        }

        return $value;
    }
}
```
