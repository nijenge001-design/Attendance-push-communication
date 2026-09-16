<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PaginatesJsonApi;
use App\Http\Requests\Api\v1\BulkImportErrorsRequest;
use App\Http\Resources\Api\v1\AttendeeImportErrorResource;
use App\Http\Resources\Api\v1\BulkResultResource;
use App\Models\AttendeeImportError;
use App\Models\Device;
use App\Services\BulkImportErrorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class AttendeeImportErrorController extends Controller
{
    use PaginatesJsonApi;
    #[Authorize('viewAny', AttendeeImportError::class)]
    public function index(Request $request)
    {
        $query = AttendeeImportError::query()
            ->accessibleBy($request->user());

        $siteIds = $request->user()->accessibleSiteIds();
        if ($siteIds !== null) {
            $serials = Device::whereIn('site_id', $siteIds)->pluck('serial_number');
            $query->whereIn('device_serial', $serials);
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        } else {
            $query->pending();
        }

        if ($device = $request->input('filter.device')) {
            $query->where('device_serial', $device);
        }

        if ($pin = $request->input('filter.pin')) {
            $query->where('pin', $pin);
        }

        $errors = $this->paginateQuery($query->latest(), $request, 20);

        return AttendeeImportErrorResource::collection($errors);
    }

    #[Authorize('view', 'attendeeImportError')]
    public function show(AttendeeImportError $attendeeImportError): AttendeeImportErrorResource
    {
        return new AttendeeImportErrorResource($attendeeImportError);
    }

    #[Authorize('resolve', 'attendeeImportError')]
    public function resolve(Request $request, AttendeeImportError $attendeeImportError): AttendeeImportErrorResource
    {
        $note = $request->input('data.attributes.adminNote');
        $attendeeImportError->markResolved($note);

        return new AttendeeImportErrorResource($attendeeImportError->fresh());
    }

    #[Authorize('ignore', 'attendeeImportError')]
    public function ignore(Request $request, AttendeeImportError $attendeeImportError): AttendeeImportErrorResource
    {
        $note = $request->input('data.attributes.adminNote');
        $attendeeImportError->markIgnored($note);

        return new AttendeeImportErrorResource($attendeeImportError->fresh());
    }

    /**
     * POST /attendee-import-errors/bulk
     */
    #[Authorize('resolveAny', AttendeeImportError::class)]
    public function bulkUpdate(BulkImportErrorsRequest $request, BulkImportErrorService $bulk): JsonResponse
    {
        $attrs = $request->validated()['data']['attributes'];

        $result = $bulk->apply(
            $request->user(),
            $attrs['ids'],
            $attrs['action'],
            $attrs['adminNote'] ?? null,
        );

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }
}
