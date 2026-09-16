<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsPlain;
use App\Models\Device;
use App\Services\DeviceDataHandler;
use App\Support\DeviceLog;
use App\Support\HybridBio;
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
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $sn = trim((string)$request->query('SN', ''));

        if ($sn === '') {
            return $this->plain('Missing SN', 400);
        }

        if (strlen($sn) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/', $sn)) {
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
        if (!$device->isApproved()) {
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
        $table = strtoupper(trim((string)$request->query('table', '')));
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
                'ATTLOG'   => $this->dataHandler->handleAttendanceLogs($sn, $content),
                'OPERLOG'  => $this->dataHandler->handleOperationLogs($sn, $content),
                'BIODATA'  => $this->dataHandler->handleBioData($sn, $content),
                'ATTPHOTO' => $this->dataHandler->handleAttendancePhoto($sn, $content),
                'USERINFO' => $this->dataHandler->handleUserInfo($sn, $content),
                'IDCARD'   => $this->dataHandler->handleIdCard($sn, $content),
                'ERRORLOG' => $this->dataHandler->handleErrorLog($sn, $content),
                'OPTIONS'  => $this->handlePushOptions($device, $content), // Hybrid Identification + device config push
                default    => $this->handleUnknownTable($sn, $table, $content),
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
            (string)$request->query('pushver', self::DEFAULT_PROTO)
        );
        $serverMax = $this->normalizeVersion(
            (string)config('iclock.push_prot_ver', '2.4.1')
        );

        return version_compare($deviceVer, $serverMax, '<=')
            ? $deviceVer
            : $serverMax;
    }

    protected function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '' || !preg_match('/^\d+(\.\d+){0,3}$/', $version)) {
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
            (string)$request->query('pushver', self::DEFAULT_PROTO)
        );
        $negotiated = $this->negotiateProtocol($request);

        $meta = [
            'pushver' => $devicePushVer,
            'negotiated' => $negotiated,
            'device_type' => $request->query('DeviceType'),
            'language' => $request->query('language'),
            'push_options_flag' => $request->query('PushOptionsFlag') === '1' ? 1 : 0,
            'handshake_at' => now()->toIso8601String(),
        ];

        $capabilities = array_merge($device->capabilities ?? [], $meta);

        // language column exists on devices table
        $updates = ['capabilities' => $capabilities];
        if ($request->filled('language') && $device->isFillable('language')) {
            $updates['language'] = (string)$request->query('language');
        }

        $device->forceFill($updates)->save();

        Cache::put(
            $this->protoCacheKey($device->serial_number),
            $negotiated,
            (int)config('iclock.proto_cache_ttl', 604800)
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
            'ErrorDelay=' . (int)config('iclock.error_delay', 30),
            'Delay=' . (int)config('iclock.delay', 10),
            'TransTimes=' . config('iclock.trans_times', '00:00;14:00'),
            'TransInterval=' . (int)config('iclock.trans_interval', 1),
            'TransFlag=' . config('iclock.trans_flag', 'TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic'),
            'TimeZone=' . config('iclock.timezone', 3),
            'Realtime=' . (int)config('iclock.realtime', 1),
            'Encrypt=None',
            'ServerVer=' . config('iclock.server_ver', '2.4.1'),
            'PushProtVer=' . $negotiated,
            'SupportPing=1',
        ];

        // Device advertised PushOptionsFlag=1 (all three real devices do)
        $deviceWantsPushOptions = $request->query('PushOptionsFlag') === '1'
            || (int)config('iclock.push_options_flag', 1) === 1;

        if ($deviceWantsPushOptions) {
            $lines[] = 'PushOptionsFlag=1';
            $pushOptions = trim((string)config('iclock.push_options', ''));
            if ($pushOptions !== '') {
                $lines[] = 'PushOptions=' . $pushOptions;
            }
        }

        // Hybrid Identification Protocol (MultiBio*) – only when negotiated version is high enough
        $dataSupport  = null;
        $photoSupport = null;
        $multiBioMin  = (string) config('iclock.multi_bio_min_ver', '2.4.1');

        if (version_compare($negotiated, $multiBioMin, '>=')) {
            $serverData  = (string) config('iclock.multi_bio_data_support', HybridBio::defaultDataSupport());
            $serverPhoto = (string) config('iclock.multi_bio_photo_support', HybridBio::defaultPhotoSupport());

            // If the device has already reported its own MultiBio* masks (via table=options),
            // advertise the intersection so both sides only use mutually supported types.
            $caps = $device->capabilities ?? [];
            $deviceData  = $caps['multi_bio_data_support']  ?? null;
            $devicePhoto = $caps['multi_bio_photo_support'] ?? null;

            $dataSupport  = is_string($deviceData)  && $deviceData  !== ''
                ? HybridBio::intersect($serverData, $deviceData)
                : $serverData;
            $photoSupport = is_string($devicePhoto) && $devicePhoto !== ''
                ? HybridBio::intersect($serverPhoto, $devicePhoto)
                : $serverPhoto;

            if ($dataSupport !== '') {
                $lines[] = 'MultiBioDataSupport=' . $dataSupport;
            }
            if ($photoSupport !== '') {
                $lines[] = 'MultiBioPhotoSupport=' . $photoSupport;
            }
        }

        DeviceLog::info($sn, 'Full handshake sent', [
            'pushver' => $request->query('pushver'),
            'negotiated' => $negotiated,
            'device_type' => $request->query('DeviceType'),
            'language' => $request->query('language'),
            'push_options_flag' => $request->query('PushOptionsFlag'),
            'multi_bio_data' => $dataSupport,
            'multi_bio_photo' => $photoSupport,
        ]);

        return $this->plain(implode("\n", $lines));
    }

    /**
     * Handle POST /iclock/cdata?table=options
     *
     * Device pushes its configuration (including Hybrid Identification parameters)
     * after receiving PushOptions=... in the handshake.
     *
     * Relevant keys (Hybrid Identification Protocol §3.1):
     *   MultiBioDataSupport, MultiBioPhotoSupport, MultiBioVersion,
     *   MaxMultiBioDataCount, MaxMultiBioPhotoCount,
     *   MultiBioDataCount, MultiBioPhotoCount,
     *   FingerFunOn, FaceFunOn, BioPhotoFun, BioDataFun, VisilightFun, ...
     */
    protected function handlePushOptions(Device $device, string $content): string
    {
        $sn = $device->serial_number;
        $parsed = $this->parseKeyValueContent($content);

        if ($parsed === []) {
            DeviceLog::warning($sn, 'Empty or unparseable options payload', [
                'bytes' => strlen($content),
            ]);

            return 'OK';
        }

        $caps = $device->capabilities ?? [];
        $hybridKeys = [
            'MultiBioDataSupport'   => 'multi_bio_data_support',
            'MultiBioPhotoSupport'  => 'multi_bio_photo_support',
            'MultiBioVersion'       => 'multi_bio_version',
            'MaxMultiBioDataCount'  => 'max_multi_bio_data_count',
            'MaxMultiBioPhotoCount' => 'max_multi_bio_photo_count',
            'MultiBioDataCount'     => 'multi_bio_data_count',
            'MultiBioPhotoCount'    => 'multi_bio_photo_count',
        ];

        $funOnKeys = [
            'FingerFunOn', 'FaceFunOn', 'FvFunOn', 'PalmFunOn', 'PhotoFunOn',
            'BioPhotoFun', 'BioDataFun', 'VisilightFun', 'UserPicURLFunOn',
        ];

        $updated = [];

        foreach ($hybridKeys as $protoKey => $capKey) {
            if (isset($parsed[$protoKey]) && $parsed[$protoKey] !== '') {
                $caps[$capKey] = $parsed[$protoKey];
                $updated[$capKey] = $parsed[$protoKey];
            }
        }

        foreach ($funOnKeys as $key) {
            if (isset($parsed[$key])) {
                $caps[strtolower($key)] = $parsed[$key];
                $updated[strtolower($key)] = $parsed[$key];
            }
        }

        // Persist any other interesting keys for debugging / future use
        foreach ($parsed as $k => $v) {
            if (! isset($hybridKeys[$k]) && ! in_array($k, $funOnKeys, true)) {
                $caps['raw_options'][$k] = $v;
            }
        }

        $caps['options_pushed_at'] = now()->toIso8601String();

        $device->forceFill(['capabilities' => $caps])->save();

        // Also update convenience boolean columns when present
        $deviceUpdates = [];
        if (isset($parsed['FingerFunOn'])) {
            $deviceUpdates['finger_fun_on'] = $parsed['FingerFunOn'] === '1';
        }
        if (isset($parsed['FaceFunOn'])) {
            $deviceUpdates['face_fun_on'] = $parsed['FaceFunOn'] === '1';
        }
        if (isset($parsed['PhotoFunOn']) || isset($parsed['BioPhotoFun'])) {
            $deviceUpdates['photo_fun_on'] = ($parsed['PhotoFunOn'] ?? $parsed['BioPhotoFun'] ?? '0') === '1';
        }
        if ($deviceUpdates !== []) {
            $device->forceFill($deviceUpdates)->save();
        }

        DeviceLog::info($sn, 'Device options (Hybrid Identification) stored', [
            'updated' => $updated,
            'enabled_data_types' => isset($caps['multi_bio_data_support'])
                ? HybridBio::enabledTypes($caps['multi_bio_data_support'])
                : null,
            'enabled_photo_types' => isset($caps['multi_bio_photo_support'])
                ? HybridBio::enabledTypes($caps['multi_bio_photo_support'])
                : null,
        ]);

        return 'OK';
    }

    /**
     * Parse key=value lines (LF or CRLF separated). Used for table=options.
     *
     * @return array<string, string>
     */
    protected function parseKeyValueContent(string $content): array
    {
        $result = [];
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim($k);
            $v = trim($v);
            if ($k !== '') {
                $result[$k] = $v;
            }
        }

        return $result;
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
