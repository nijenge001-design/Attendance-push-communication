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
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Handles /iclock/cdata
 *
 * GET  → initialization / handshake (options exchange) with protocol negotiation
 * POST → data upload (ATTLOG, OPERLOG, BIODATA, ATTPHOTO, USERINFO, …)
 *
 * Real devices observed:
 *   ?options=all&pushver=2.4.1&PushOptionsFlag=1&DeviceType=middle%20east&language=69&SN=...
 *   ?options=all&pushver=2.4.0&PushOptionsFlag=1&DeviceType=middle%20east&language=69&SN=...
 */
class CdataController extends Controller
{
    use RespondsPlain;

    private const string DEFAULT_PROTO = '2.2.14';

    public function __construct(
        private readonly DeviceDataHandler $dataHandler,
    ) {}

    public function __invoke(Request $request): Response
    {
        $sn = trim((string) $request->query('SN', ''));

        if ($sn === '') {
            return $this->plain('Missing SN', 400);
        }

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

        $device->heartbeat('cdata', $request->ip());

        // Persist handshake metadata on any GET options exchange
        if ($request->isMethod('GET') && $request->query('options') === 'all') {
            $this->persistHandshakeMeta($device, $request);
        }

        // ------------------------------------------------------------------
        // Unapproved / blocked devices
        // ------------------------------------------------------------------
        if (! $device->isApproved()) {
            if ($request->isMethod('GET')) {
                DeviceLog::info($sn, 'Handshake denied – device not approved', [
                    'status' => $device->status,
                    'pushver' => $request->query('pushver'),
                ]);

                return $this->disabledHandshake($device, $request);
            }

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

            return $this->plain('OK');
        }

        return $this->plain($result);
    }

    // =====================================================================
    // Protocol negotiation
    // =====================================================================

    /**
     * min(device pushver, server max). Default 2.2.14 when missing.
     */
    protected function negotiateProtocol(Request $request): string
    {
        $deviceVer = $this->normalizeVersion(
            (string) $request->query('pushver', self::DEFAULT_PROTO)
        );
        $serverMax = $this->normalizeVersion(
            (string) config('iclock.push_prot_ver', '2.4.1')
        );

        return version_compare($deviceVer, $serverMax, '<=')
            ? $deviceVer
            : $serverMax;
    }

    protected function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '' || ! preg_match('/^\d+(\.\d+){0,3}$/', $version)) {
            return self::DEFAULT_PROTO;
        }

        return $version;
    }

    /**
     * Store pushver / negotiated / DeviceType / language on device + cache.
     */
    protected function persistHandshakeMeta(Device $device, Request $request): void
    {
        $devicePushVer = $this->normalizeVersion(
            (string) $request->query('pushver', self::DEFAULT_PROTO)
        );
        $negotiated = $this->negotiateProtocol($request);

        $meta = [
            'pushver' => $devicePushVer,
            'negotiated' => $negotiated,
            'device_type' => $request->query('DeviceType'),
            'language' => $request->query('language'),
            'push_options_flag' => $request->query('PushOptionsFlag') == '1' ? 1 : 0,
            'handshake_at' => now()->toIso8601String(),
        ];

        $capabilities = array_merge($device->capabilities ?? [], $meta);

        // language column exists on devices table
        $updates = ['capabilities' => $capabilities];
        if ($request->filled('language') && $device->isFillable('language')) {
            $updates['language'] = (string) $request->query('language');
        }

        $device->forceFill($updates)->save();

        Cache::put(
            $this->protoCacheKey($device->serial_number),
            $negotiated,
            (int) config('iclock.proto_cache_ttl', 604800)
        );

        DeviceLog::info($device->serial_number, 'Handshake meta stored', $meta);
    }

    protected function protoCacheKey(string $sn): string
    {
        return "iclock:proto:{$sn}";
    }

    protected function cachedNegotiated(string $sn): ?string
    {
        $v = Cache::get($this->protoCacheKey($sn));

        return is_string($v) ? $v : null;
    }

    // =====================================================================
    // Handshake helpers
    // =====================================================================

    /**
     * Restricted handshake for pending/blocked devices.
     */
    protected function disabledHandshake(Device $device, Request $request): Response
    {
        $sn = $device->serial_number;
        $negotiated = $this->negotiateProtocol($request);

        $lines = [
            "GET OPTION FROM: {$sn}",
            'ATTLOGStamp=None',
            'OPERLOGStamp=None',
            'BIODATAStamp=None',
            'ATTPHOTOStamp=None',
            'ErrorDelay=60',
            'Delay=30',
            'TransFlag=0',
            'Realtime=0',
            'ServerVer=' . config('iclock.server_ver', '2.4.1'),
            'PushProtVer=' . $negotiated,
        ];

        return $this->plain(implode("\n", $lines));
    }

    /**
     * Full option exchange for approved devices.
     */
    protected function fullHandshake(Device $device, Request $request): Response
    {
        $sn = $device->serial_number;
        $negotiated = $this->negotiateProtocol($request);

        // Future: real stamps from DB/cache for resume
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
            'ErrorDelay=' . (int) config('iclock.error_delay', 30),
            'Delay=' . (int) config('iclock.delay', 10),
            'TransTimes=' . config('iclock.trans_times', '00:00;14:00'),
            'TransInterval=' . (int) config('iclock.trans_interval', 1),
            'TransFlag=' . config('iclock.trans_flag', 'TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic'),
            'TimeZone=' . config('iclock.timezone', 3),
            'Realtime=' . (int) config('iclock.realtime', 1),
            'Encrypt=None',
            'ServerVer=' . config('iclock.server_ver', '2.4.1'),
            'PushProtVer=' . $negotiated,
            'SupportPing=1',
        ];

        // Device advertised PushOptionsFlag=1 (all three real devices do)
        $deviceWantsPushOptions = $request->query('PushOptionsFlag') == '1'
            || (int) config('iclock.push_options_flag', 1) === 1;

        if ($deviceWantsPushOptions) {
            $lines[] = 'PushOptionsFlag=1';
            $pushOptions = trim((string) config('iclock.push_options', ''));
            if ($pushOptions !== '') {
                $lines[] = 'PushOptions=' . $pushOptions;
            }
        }

        // Multi-bio only when negotiated version is high enough
        $multiBioMin = (string) config('iclock.multi_bio_min_ver', '2.4.1');
        if (version_compare($negotiated, $multiBioMin, '>=')) {
            $dataSupport = config('iclock.multi_bio_data_support');
            $photoSupport = config('iclock.multi_bio_photo_support');
            if (is_string($dataSupport) && $dataSupport !== '') {
                $lines[] = 'MultiBioDataSupport=' . $dataSupport;
            }
            if (is_string($photoSupport) && $photoSupport !== '') {
                $lines[] = 'MultiBioPhotoSupport=' . $photoSupport;
            }
        }

        DeviceLog::info($sn, 'Full handshake sent', [
            'pushver' => $request->query('pushver'),
            'negotiated' => $negotiated,
            'device_type' => $request->query('DeviceType'),
            'language' => $request->query('language'),
            'push_options_flag' => $request->query('PushOptionsFlag'),
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
