<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RespondsPlain;
use App\Models\Device;
use App\Services\CommandResponseHandler;
use App\Support\DeviceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * POST /iclock/devicecmd?SN=...
 *
 * Device reports results of previously issued commands.
 *
 * Body examples:
 *   ID=abc123&Return=0&CMD=INFO
 *   ID=abc123&Return=0&CMD=DATA
 *   (optional extra lines with query result body)
 *
 * Multiple replies are separated by LF.
 */
class DeviceCmdController extends Controller
{
    use RespondsPlain;

    public function __construct(
        private readonly CommandResponseHandler $responseHandler,
    ) {}

    public function __invoke(Request $request): Response
    {
        $sn = trim((string) $request->query('SN', ''));

        if ($sn === '') {
            return $this->plain('OK');
        }

        $device = Device::where('serial_number', $sn)->first();
        if ($device) {
            $device->heartbeat('devicecmd', $request->ip());
        }

        $body = $request->getContent() ?: '';
        if (trim($body) === '') {
            return $this->plain('OK');
        }

        DeviceLog::debug($sn, 'devicecmd received', [
            'bytes' => strlen($body),
        ]);

        // Split into individual reply records (LF separated)
        $records = preg_split("/\r\n|\n|\r/", $body) ?: [];

        // Group lines that belong to the same command.
        // Typical shape: first line is ID=...&Return=...&CMD=...
        // followed by zero or more body lines until the next ID= line.
        $currentId = null;
        $currentLines = [];

        $flush = function () use (&$currentId, &$currentLines, $sn) {
            if ($currentId === null) {
                return;
            }
            try {
                $this->responseHandler->saveResponse($currentId, $currentLines);
            } catch (Throwable $e) {
                DeviceLog::error($sn, 'Failed to process command reply', [
                    'command_id' => $currentId,
                    'error' => $e->getMessage(),
                ]);
            }
            $currentId = null;
            $currentLines = [];
        };

        foreach ($records as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'ID=')) {
                $flush();

                parse_str($line, $params);
                $currentId = $params['ID'] ?? null;
                $currentLines = [$line];

                if ($currentId === null || $currentId === '') {
                    $currentId = null;
                    $currentLines = [];
                }

                continue;
            }

            // Continuation / body line of the current command
            if ($currentId !== null) {
                $currentLines[] = $line;
            }
        }

        $flush();

        return $this->plain('OK');
    }
}
