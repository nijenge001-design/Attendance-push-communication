```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Iclock;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Iclock\Concerns\RespondsPlain;
use App\Models\Device;
use App\Services\DeviceDataHandler;
use App\Support\DeviceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Handles /iclock/cdata
 *
 * GET  → initialization / handshake (options exchange)
 * POST → data upload (ATTLOG, OPERLOG, BIODATA, ATTPHOTO, USERINFO, …)
 */
class CdataController extends Controller
{
    use RespondsPlain;

    public function __construct(
        private readonly DeviceDataHandler $dataHandler,
    ) {}

    public function __invoke(Request $request): Response
    {
        $sn = trim((string) $request->query('SN', ''));

        if ($sn === '') {
            return $this->plain('Missing SN', 400);
        }

        // Basic SN sanity – reject obviously invalid values early
        if (strlen($sn) > 64 || ! preg_match('/^[A-Za-z0-9_-]+$/', $sn)) {
            return $this->plain('Invalid SN', 400);
        }

        try {
            $device = Device::firstOrCreate(
                ['serial_number' => $sn],
                ['status' => Device::STATUS_PENDING]
            );
        } catch (Throwable $e) {
            DeviceLog::error($sn, 'Device firstOrCreate failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->plain('ERROR', 500);
        }

        // Heartbeat + optional IP capture
        $device->heartbeat('cdata', $request->ip());

        // ------------------------------------------------------------------
        // Unapproved / blocked devices
        // ------------------------------------------------------------------
        if (! $device->isApproved()) {
            if ($request->isMethod('GET')) {
                DeviceLog::info($sn, 'Handshake denied – device not approved', [
                    'status' => $device->status,
                ]);

                return $this->disabledHandshake($sn);
            }

            // Consume body so PHP doesn’t leave the stream open, then ACK
            $request->getContent();

            return $this->plain('OK');
        }

        // ------------------------------------------------------------------
        // GET = full handshake / option exchange
        // ------------------------------------------------------------------
        if ($request->isMethod('GET')) {
            return $this->fullHandshake($device, $request);
        }

        // ------------------------------------------------------------------
        // POST = data table upload
        // ------------------------------------------------------------------
        $table = strtoupper(trim((string) $request->query('table', '')));
        $content = $request->getContent() ?: '';

        if ($table === '') {
            // Some firmwares POST without a table (keep-alive style)
            return $this->plain('OK');
        }

        DeviceLog::debug($sn, 'cdata POST', [
            'table' => $table,
            'bytes' => strlen($content),
            'stamp' => $request->query('Stamp'),
        ]);

        try {
            $result = match ($table) {
                'ATTLOG' => $this->dataHandler->handleAttendanceLogs($sn, $content),
                'OPERLOG' => $this->dataHandler->handleOperationLogs($sn, $content),
                'BIODATA' => $this->dataHandler->handleBioData($sn, $content),
                'ATTPHOTO' => $this->dataHandler->handleAttendancePhoto($sn, $content),
                'USERINFO' => $this->dataHandler->handleUserInfo($sn, $content),
                'IDCARD' => $this->dataHandler->handleIdCard($sn, $content),
                'ERRORLOG' => $this->dataHandler->handleErrorLog($sn, $content),
                default => $this->handleUnknownTable($sn, $table, $content),
            };
        } catch (Throwable $e) {
            DeviceLog::error($sn, 'cdata handler exception', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            // Still ACK so the device does not retry aggressively
            return $this->plain('OK');
        }

        return $this->plain($result);
    }

    // =====================================================================
    // Handshake helpers
    // =====================================================================

    /**
     * Response for devices that are not yet approved.
     * Keeps the connection alive but disables data transfer.
     */
    protected function disabledHandshake(string $sn): Response
    {
        $body = implode("\n", [
            "GET OPTION FROM: {$sn}",
            'ATTLOGStamp=None',
            'OPERLOGStamp=None',
            'BIODATAStamp=None',
            'ATTPHOTOStamp=None',
            'ErrorDelay=60',
            'Delay=30',
            'TransFlag=0',
            'Realtime=0',
            'ServerVer=' . config('app.version', '3.0.1'),
            'PushProtVer=2.4.1',
        ]);

        return $this->plain($body);
    }

    /**
     * Full option exchange for approved devices.
     * Stamps can later be driven from DB for true resume support.
     */
    protected function fullHandshake(Device $device, Request $request): Response
    {
        $sn = $device->serial_number;

        // Future: load real stamps from a device_stamps table or cache
        $attlogStamp = '0';
        $operlogStamp = '0';
        $biodataStamp = '0';
        $attphotoStamp = '0';

        $lines = [
            "GET OPTION FROM: {$sn}",
            "ATTLOGStamp={$attlogStamp}",
            "OPERLOGStamp={$operlogStamp}",
            "BIODATAStamp={$biodataStamp}",
            "ATTPHOTOStamp={$attphotoStamp}",
            'ErrorDelay=' . config('iclock.error_delay', 30),
            'Delay=' . config('iclock.delay', 10),
            'TransTimes=' . config('iclock.trans_times', '00:00;14:00'),
            'TransInterval=' . config('iclock.trans_interval', 1),
            'TransFlag=' . config('iclock.trans_flag', 'TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic'),
            'TimeZone=' . config('iclock.timezone', config('app.timezone', 'UTC')),
            'Realtime=' . config('iclock.realtime', 1),
            'Encrypt=None',
            'ServerVer=' . config('app.version', '3.0.1'),
            'PushProtVer=2.4.1',
            'SupportPing=1',
        ];

        // If device asked for PushOptionsFlag extras
        if ($request->query('PushOptionsFlag') == '1') {
            $lines[] = 'PushOptionsFlag=1';
        }

        DeviceLog::info($sn, 'Full handshake sent', [
            'options' => $request->query('options'),
            'pushver' => $request->query('pushver'),
        ]);

        return $this->plain(implode("\n", $lines));
    }

    // =====================================================================
    // Internals
    // =====================================================================

    protected function handleUnknownTable(string $sn, string $table, string $content): string
    {
        DeviceLog::warning($sn, 'Unknown cdata table', [
            'table' => $table,
            'bytes' => strlen($content),
        ]);

        return 'OK';
    }
}
```
