```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Iclock;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Iclock\Concerns\RespondsPlain;
use App\Models\Device;
use App\Support\DeviceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET|POST /iclock/ping?SN=...
 *
 * Lightweight heartbeat while the device is busy with large uploads.
 */
class PingController extends Controller
{
    use RespondsPlain;

    public function __invoke(Request $request): Response
    {
        $sn = trim((string) $request->query('SN', ''));

        if ($sn !== '') {
            $device = Device::where('serial_number', $sn)->first();
            if ($device) {
                $device->heartbeat('ping', $request->ip());
                DeviceLog::debug($sn, 'ping');
            }
        }

        return $this->plain('OK');
    }
}
```
