<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RespondsPlain;
use App\Models\Device;
use App\Models\PendingCommand;
use App\Support\DeviceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * GET /iclock/getrequest?SN=...
 *
 * Device polls for pending commands. Also used to push INFO stats via &INFO=.
 * Response: "OK" or one-or-more "C:{cmdId}:{command}\n" lines.
 */
class GetRequestController extends Controller
{
    use RespondsPlain;

    public function __invoke(Request $request): Response
    {
        $sn = trim((string) $request->query('SN', ''));

        if ($sn === '' || strlen($sn) > 64 || ! preg_match('/^[A-Za-z0-9_-]+$/', $sn)) {
            return $this->plain('OK');
        }

        $device = Device::where('serial_number', $sn)->first();

        if (! $device) {
            // Unknown device – create as pending so admin can approve later
            try {
                $device = Device::create([
                    'serial_number' => $sn,
                    'status' => Device::STATUS_PENDING,
                ]);
            } catch (Throwable) {
                return $this->plain('OK');
            }
        }

        $device->heartbeat('getrequest', $request->ip());

        // Optional INFO payload: firmware, counts, IP, algorithms, feature flags
        if ($info = $request->query('INFO')) {
            $this->applyInfoPayload($device, (string) $info);
        }

        if (! $device->isApproved()) {
            DeviceLog::debug($sn, 'getrequest – not approved, no commands');

            return $this->plain('OK');
        }

        // Fetch commands ready to send (respects available_at, scheduled_at, recurring)
        $commands = PendingCommand::forDevice($sn)
            ->readyToSend()
            ->orderBy('created_at')
            ->limit((int) config('iclock.max_commands_per_poll', 20))
            ->get();

        if ($commands->isEmpty()) {
            return $this->plain('OK');
        }

        $lines = [];

        foreach ($commands as $cmd) {
            // command_text should already be in "C:{id}:{body}" form
            $text = trim((string) $cmd->command_text);
            if ($text === '') {
                continue;
            }

            // Ensure prefix is present
            if (! str_starts_with($text, 'C:')) {
                $text = 'C:' . $cmd->command_id . ':' . $text;
            }

            $lines[] = $text;
            $cmd->markAsSent();
        }

        if (empty($lines)) {
            return $this->plain('OK');
        }

        DeviceLog::info($sn, 'Commands dispatched', [
            'count' => count($lines),
            'ids' => $commands->pluck('command_id')->all(),
        ]);

        // Protocol: multiple commands separated by LF
        return $this->plain(implode("\n", $lines));
    }

    /**
     * Parse INFO=fw,userCount,fpCount,attCount,ip,fpAlg,faceAlg,faceNeed,faceCount,flags
     */
    private function applyInfoPayload(Device $device, string $info): void
    {
        $parts = array_map('trim', explode(',', $info));

        $updates = [];

        if (isset($parts[0]) && $parts[0] !== '') {
            $updates['firmware_version'] = $parts[0];
        }
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $updates['user_count'] = (int) $parts[1];
        }
        if (isset($parts[2]) && is_numeric($parts[2])) {
            $updates['fp_count'] = (int) $parts[2];
        }
        if (isset($parts[3]) && is_numeric($parts[3])) {
            $updates['attlog_count'] = (int) $parts[3];
        }
        if (isset($parts[4]) && $parts[4] !== '') {
            $updates['ip'] = $parts[4];
        }
        if (isset($parts[8]) && is_numeric($parts[8])) {
            $updates['face_count'] = (int) $parts[8];
        }

        // Feature flags (digit string, e.g. 111)
        if (isset($parts[9]) && $parts[9] !== '') {
            $flags = (string) $parts[9];
            $updates['finger_fun_on'] = isset($flags[0]) ? $flags[0] === '1' : null;
            $updates['face_fun_on'] = isset($flags[1]) ? $flags[1] === '1' : null;
            $updates['photo_fun_on'] = isset($flags[2]) ? $flags[2] === '1' : null;
        }

        if ($updates !== []) {
            $device->fill(array_filter($updates, fn ($v) => $v !== null))->save();

            DeviceLog::debug($device->serial_number, 'INFO payload applied', [
                'fields' => array_keys($updates),
            ]);
        }
    }
}
