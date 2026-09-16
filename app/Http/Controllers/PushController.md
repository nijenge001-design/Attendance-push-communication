```php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\PendingCommand;
use App\Services\CommandResponseHandler;
use App\Services\DeviceDataHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushController extends Controller
{
    public function __construct(
        private readonly DeviceDataHandler      $dataHandler,
        private readonly CommandResponseHandler $responseHandler
    )
    {
    }

    public function cdata(Request $request): Response
    {
        $sn = $request->query('SN');
        if (!$sn) {
            return response('Missing SN', 400);
        }

        $device = Device::firstOrCreate(
            ['serial_number' => $sn],
            ['status' => Device::STATUS_PENDING]
        );

        $device->heartbeat('cdata');

        if (!$device->isApproved()) {
            if ($request->isMethod('get')) {
                return $this->disabledHandshake($sn);
            }
            $request->getContent(); // consume body
            return response(null);
        }

        if ($request->isMethod('get')) {
            return $this->fullHandshake($device);
        }

        $table = $request->query('table');
        $content = $request->getContent();

        if ($table === null) {
            return $this->plainResponse('OK');
        }

        $result = match ($table) {
            'ATTLOG' => $this->dataHandler->handleAttendanceLogs($sn, $content),
            'OPERLOG' => $this->dataHandler->handleOperationLogs($sn, $content),
            'BIODATA' => $this->dataHandler->handleBioData($sn, $content),
            'ATTPHOTO' => $this->dataHandler->handleAttendancePhoto($sn, $content),
            'USERINFO' => $this->dataHandler->handleUserInfo($sn, $content),
            'IDCARD' => $this->dataHandler->handleIdCard($sn, $content),
            'ERRORLOG' => $this->dataHandler->handleErrorLog($sn, $content),
            default => 'OK',
        };

        return $this->plainResponse($result);
    }

    /**
     * Device polls for pending commands.
     * Also accepts optional INFO parameter with device status.
     */
    public function getrequest(Request $request): Response
    {
        $sn = $request->query('SN');

        if (!$sn) {
            return response('Missing SN', 400);
        }

        $device = Device::firstOrCreate(
            ['serial_number' => $sn],
            ['status' => Device::STATUS_PENDING]
        );

        $device->heartbeat('getrequest');
        // Update device info if INFO parameter is present
        if ($info = $request->query('INFO')) {
            $this->updateDeviceFromInfo($device, $info);
        }

        if (!$device->isApproved()) {
            return $this->plainResponse('OK');
        }

        $pending = PendingCommand::forDevice($sn)
            ->readyToSend()
            ->enabled()
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return $this->plainResponse('OK');
        }

        // Mark as sent so they are not delivered again until executed
        $pending->each->markAsSent();

        $body = $pending->pluck('command_text')->implode("\n") . "\n";

        return $this->plainResponse($body);
    }

    /**
     * Return a plain-text response with the correct Date header (GMT)
     * so devices can synchronize their clock.
     */
    private function plainResponse(string $body, int $status = 200): Response
    {
        return response($body, $status)
            ->header('Content-Type', 'text/plain')
            ->header('Date', gmdate('D, d M Y H:i:s') . ' GMT');
    }

    /**
     * Device reports command execution results.
     */
    public function devicecmd(Request $request): Response
    {
        $sn = $request->query('SN');
        if (!$sn) {
            return response('Missing SN', 400);
        }

        $content = trim($request->getContent());
        $lines = array_filter(explode("\n", $content));

        $currentCmdId = null;
        $responseBody = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'ID=')) {
                // Save previous command response
                if ($currentCmdId && !empty($responseBody)) {
                    $this->responseHandler->saveResponse($currentCmdId, $responseBody);
                }

                parse_str($line, $params);
                $currentCmdId = $params['ID'] ?? null;
                $responseBody = [$line];
                continue;
            }

            if ($currentCmdId) {
                $responseBody[] = $line;
            }
        }

        // Save the last command
        if ($currentCmdId && !empty($responseBody)) {
            $this->responseHandler->saveResponse($currentCmdId, $responseBody);
        }

        return response('OK', 200);
    }

    public function ping(Request $request): Response
    {
        $sn = $request->query('SN');
        if ($sn) {
            $device = Device::firstOrCreate(
                ['serial_number' => $sn],
                ['status' => Device::STATUS_PENDING]
            );
            $device->heartbeat('ping');
        }

        return $this->plainResponse('OK');
    }

    public function exchange(Request $request): Response
    {
        return $this->plainResponse('OK');
    }

    // =====================================================================
    // Private helpers
    // =====================================================================

    /**
     * Parse INFO parameter from getrequest and update device.
     * Format: FirmwareVer,UserCount,FingerCount,AttLogCount,IP,FpAlgVer,FaceAlgVer,FaceNeed,FaceCount,FuncFlags
     */
    private function updateDeviceFromInfo(Device $device, string $info): void
    {
        $parts = explode(',', $info);

        $device->ip = $parts[4] ?? $device->ip;

        $capabilities = $device->capabilities ?? [];
        $capabilities = array_merge($capabilities, array_filter([
            'firmware' => $parts[0] ?? null,
            'user_count' => isset($parts[1]) ? (int)$parts[1] : null,
            'finger_count' => isset($parts[2]) ? (int)$parts[2] : null,
            'attlog_count' => isset($parts[3]) ? (int)$parts[3] : null,
            'fp_alg_ver' => $parts[5] ?? null,
            'face_alg_ver' => $parts[6] ?? null,
            'face_need' => isset($parts[7]) ? (int)$parts[7] : null,
            'face_count' => isset($parts[8]) ? (int)$parts[8] : null,
            'func_flags' => $parts[9] ?? null,
        ], fn($v) => $v !== null));

        $device->capabilities = $capabilities;
        $device->save();
    }

    private function disabledHandshake(string $sn): Response
    {
        $lines = [
            "GET OPTION FROM: {$sn}",
            'ATLOGStamp=0',
            'OPERLOGStamp=0',
            'ATTPHOTOStamp=0',
            'BIODATAStamp=0',
            'ErrorDelay=300',
            'Delay=300',
            'TransTimes=',
            'TransInterval=0',
            'TransFlag=0000000000',
            'TimeZone=2',
            'Realtime=0',
            'Encrypt=None',
            'ServerVer=2.4.1',
            'PushProtVer=2.4.1',
            'PushOptionsFlag=0',
        ];

        return $this->plainResponse(implode("\n", $lines));
    }

    private function fullHandshake(Device $device): Response
    {
        $sn = $device->serial_number;

        $lines = [
            "GET OPTION FROM: {$sn}",
            'ATLOGStamp=0',
            'OPERLOGStamp=0',
            'ATTPHOTOStamp=0',
            'BIODATAStamp=0',
            'ErrorDelay=60',
            'Delay=10',
            'TransTimes=00:00;12:00',
            'TransInterval=1',
            'TransFlag=TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic BioPhoto',
            'TimeZone=2',          // adjust to your real timezone offset if needed
            'Realtime=1',
            'Encrypt=None',
            'ServerVer=2.4.1',
            'PushProtVer=2.4.1',
            'PushOptionsFlag=1',
            'PushOptions=FingerFunOn,FaceFunOn',
        ];

        return $this->plainResponse(implode("\n", $lines));
    }
}

```
