<?php

declare(strict_types=1);

namespace App\Services\Imports;

final class ColumnMapper
{
    public const ATTENDEE_ALIASES = [
        'pin' => ['pin', 'employeeid', 'employee_id', 'emp_id', 'userid', 'user_id', 'userpin', 'personnel_id'],
        'name' => ['name', 'fullname', 'full_name', 'employee_name', 'employeename', 'nama'],
        'privilege' => ['privilege', 'priv', 'role', 'level'],
        'password' => ['password', 'pwd', 'pass'],
        'cardNumber' => ['cardnumber', 'card_number', 'card', 'cardno', 'card_no', 'rfid'],
        'viceCard' => ['vicecard', 'vice_card', 'card2'],
        'groupId' => ['groupid', 'group_id', 'group'],
        'timezone' => ['timezone', 'time_zone', 'tz'],
        'verificationMode' => ['verificationmode', 'verification_mode', 'verify', 'verifymode', 'verify_mode'],
        'siteIds' => ['siteids', 'site_ids', 'sites', 'siteid', 'site_id'],
        'deviceSerials' => ['deviceserials', 'device_serials', 'devices', 'serials', 'device', 'serial', 'sn'],
    ];

    public const ATTENDANCE_ALIASES = [
        'pin' => ['pin', 'employeeid', 'employee_id', 'userid', 'user_id'],
        'timestamp' => ['timestamp', 'time', 'datetime', 'date_time', 'punch_time', 'punchtime', 'clock'],
        'status' => ['status', 'punch_status', 'in_out', 'inout'],
        'verifyMode' => ['verifymode', 'verify_mode', 'verify', 'verificationmode'],
        'deviceSerial' => ['deviceserial', 'device_serial', 'device', 'serial', 'sn', 'terminal'],
        'workcode' => ['workcode', 'work_code', 'wc'],
        'type' => ['type'],
        'maskFlag' => ['maskflag', 'mask_flag', 'mask'],
        'temperature' => ['temperature', 'temp'],
        'convTemperature' => ['convtemperature', 'conv_temperature'],
        'idNumber' => ['idnumber', 'id_number', 'idnum', 'national_id'],
    ];

    /**
     * @param  list<string>  $headers
     * @param  array<string, list<string>>  $aliases
     * @return array<string, int>  field => column index
     */
    public function map(array $headers, array $aliases): array
    {
        $normalized = [];
        foreach ($headers as $i => $header) {
            $normalized[$i] = $this->normalize((string) $header);
        }

        $map = [];
        foreach ($aliases as $field => $names) {
            foreach ($normalized as $i => $header) {
                if (in_array($header, $names, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    public function normalize(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace([' ', '-', '.', '/'], '_', $header);

        return preg_replace('/_+/', '_', $header) ?? $header;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>
     */
    public function rowToItem(array $row, array $map): array
    {
        $item = [];
        foreach ($map as $field => $index) {
            $item[$field] = $row[$index] ?? '';
        }

        return $item;
    }

    /**
     * @return list<string>
     */
    public function explodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== ''));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;|\/]+/', $raw) ?: [])));
    }
}
