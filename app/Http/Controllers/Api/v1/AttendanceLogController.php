<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\AttendancePunched;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PaginatesJsonApi;
use App\Http\Requests\Api\v1\BulkStoreAttendanceLogsRequest;
use App\Http\Requests\Api\v1\ImportSpreadsheetRequest;
use App\Http\Resources\Api\v1\AttendanceLogResource;
use App\Http\Resources\Api\v1\BulkResultResource;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Services\BulkAttendanceLogService;
use App\Services\Imports\SpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use InvalidArgumentException;

class AttendanceLogController extends Controller
{
    use PaginatesJsonApi;

    #[Authorize('viewAny', AttendanceLog::class)]
    public function index(Request $request)
    {
        $query = AttendanceLog::query()
            ->accessibleBy($request->user());

        $siteIds = $request->user()->accessibleSiteIds();
        if ($siteIds !== null) {
            $serials = Device::whereIn('site_id', $siteIds)->pluck('serial_number');
            $query->whereIn('device_serial', $serials);
        }

        if ($pin = $request->input('filter.pin')) {
            $query->forPin($pin);
        }

        if ($device = $request->input('filter.device')) {
            $query->forDevice($device);
        }

        if ($request->boolean('filter.today')) {
            $query->today();
        }

        if ($start = $request->input('filter.start')) {
            $end = $request->input('filter.end', now());
            $query->betweenDates($start, $end);
        }

        if ($request->boolean('filter.withMask')) {
            $query->withMask();
        }

        if ($request->boolean('filter.hasTemperature')) {
            $query->hasTemperature();
        }

        $logs = $this->paginateQuery(
            $query->latest('timestamp'),
            $request,
            50,
        );

        return AttendanceLogResource::collection($logs);
    }

    #[Authorize('view', 'attendanceLog')]
    public function show(AttendanceLog $attendanceLog): AttendanceLogResource
    {
        return new AttendanceLogResource($attendanceLog);
    }

    /**
     * Route: POST /devices/{device}/attendance-logs
     *
     * Array syntax: ['device', AttendanceLog::class]
     *   - 'device'               → route-bound Device passed as first arg
     *   - AttendanceLog::class   → policy target class
     *
     * The `deviceSerial` in the request body must match the route-bound device.
     */
    #[Authorize('create', ['device', AttendanceLog::class])]
    public function store(Request $request, Device $device): AttendanceLogResource
    {
        $validated = $request->validate([
            'data.attributes.pin' => 'required|string',
            'data.attributes.timestamp' => 'required|date',
            'data.attributes.status' => 'required|integer',
            'data.attributes.verifyMode' => 'required|integer',
            'data.attributes.workcode' => 'nullable|string',
            'data.attributes.type' => 'integer',
            'data.attributes.maskFlag' => 'nullable|integer',
            'data.attributes.temperature' => 'nullable|numeric',
            'data.attributes.convTemperature' => 'nullable|numeric',
        ]);

        $attrs = $validated['data']['attributes'];

        $log = AttendanceLog::create([
            'pin' => $attrs['pin'],
            'device_serial' => $device->serial_number,
            'timestamp' => $attrs['timestamp'],
            'status' => $attrs['status'],
            'verify_mode' => $attrs['verifyMode'],
            'workcode' => $attrs['workcode'] ?? null,
            'type' => $attrs['type'] ?? 0,
            'mask_flag' => $attrs['maskFlag'] ?? null,
            'temperature' => $attrs['temperature'] ?? null,
            'conv_temperature' => $attrs['convTemperature'] ?? null,
        ]);

        event(new AttendancePunched($log));

        return new AttendanceLogResource($log);
    }

    /**
     * POST /devices/{device}/attendance-logs/bulk
     */
    #[Authorize('create', ['device', AttendanceLog::class])]
    public function bulkStoreForDevice(
        BulkStoreAttendanceLogsRequest $request,
        Device $device,
        BulkAttendanceLogService $bulk,
    ): JsonResponse {
        $attrs = $request->validated()['data']['attributes'];

        $result = $bulk->store(
            $request->user(),
            $attrs['items'],
            $device,
            (bool) ($attrs['dispatchJobs'] ?? true),
            (bool) ($attrs['broadcast'] ?? false),
        );

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    /**
     * POST /attendance-logs/bulk
     *
     * Mixed devices; each item must include deviceSerial.
     */
    #[Authorize('createBulk', AttendanceLog::class)]
    public function bulkStore(
        BulkStoreAttendanceLogsRequest $request,
        BulkAttendanceLogService $bulk,
    ): JsonResponse {
        $attrs = $request->validated()['data']['attributes'];

        $result = $bulk->store(
            $request->user(),
            $attrs['items'],
            null,
            (bool) ($attrs['dispatchJobs'] ?? true),
            (bool) ($attrs['broadcast'] ?? false),
        );

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    /**
     * GET /api/v1/attendance-logs/import-template?format=xlsx|csv
     */
    #[Authorize('createBulk', AttendanceLog::class)]
    public function importTemplate(Request $request, SpreadsheetImportService $import)
    {
        $format = $request->input('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        $file = $import->downloadTemplate('attendance', $format);

        return response($file['body'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }

    /**
     * POST /api/v1/attendance-logs/import
     */
    #[Authorize('createBulk', AttendanceLog::class)]
    public function import(ImportSpreadsheetRequest $request, SpreadsheetImportService $import): JsonResponse
    {
        try {
            $result = $import->importAttendanceLogs($request->user(), $request->file('file'), [
                'dryRun' => $request->boolean('dryRun'),
                'dispatchJobs' => $request->boolean('dispatchJobs', true),
                'broadcast' => $request->boolean('broadcast'),
                'deviceSerial' => $request->input('deviceSerial') ?: null,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'errors' => [[
                    'status' => '422',
                    'title' => 'Invalid Spreadsheet',
                    'detail' => $e->getMessage(),
                ]],
            ], 422)->header('Content-Type', 'application/vnd.api+json');
        }

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    #[Authorize('delete', 'attendanceLog')]
    public function destroy(AttendanceLog $attendanceLog)
    {
        $attendanceLog->delete();

        return response()->json(null, 204);
    }
}
