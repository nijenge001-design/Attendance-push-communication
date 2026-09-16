<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\AttendeeAccessGranted;
use App\Events\AttendeeAccessRevoked;
use App\Events\AttendeeCreated;
use App\Events\AttendeeDeleted;
use App\Events\AttendeeUpdated;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PaginatesJsonApi;
use App\Http\Requests\Api\v1\BulkAttendeeAccessRequest;
use App\Http\Requests\Api\v1\BulkUpsertAttendeesRequest;
use App\Http\Requests\Api\v1\ImportSpreadsheetRequest;
use App\Http\Resources\Api\v1\AttendeeResource;
use App\Http\Resources\Api\v1\BulkResultResource;
use App\Models\Attendee;
use App\Services\BulkAttendeeService;
use App\Services\Imports\SpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class AttendeeController extends Controller
{
    use PaginatesJsonApi;
    #[Authorize('viewAny', Attendee::class)]
    public function index(Request $request)
    {
        $query = Attendee::query()
            ->accessibleBy($request->user());

        $siteIds = $request->user()->accessibleSiteIds();
        if ($siteIds !== null) {
            $query->where(function ($q) use ($siteIds) {
                $q->whereHas('sites', fn($s) => $s->whereIn('sites.id', $siteIds))
                    ->orWhereHas('devices', fn($d) => $d->whereIn('site_id', $siteIds));
            });
        }

        if ($pin = $request->input('filter.pin')) {
            $query->where('pin', $pin);
        }

        if ($deviceSerial = $request->input('filter.device')) {
            $query->onDevice($deviceSerial);
        }

        if ($search = $request->input('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('pin', 'like', "%{$search}%")
                    ->orWhere('card_number', 'like', "%{$search}%");
            });
        }

        $attendees = $this->paginateQuery($query->latest(), $request, 15);

        return AttendeeResource::collection($attendees);
    }

    #[Authorize('create', Attendee::class)]
    public function store(Request $request): AttendeeResource
    {
        $validated = $request->validate([
            'data.attributes.pin' => 'required|string|unique:attendees,pin',
            'data.attributes.name' => 'nullable|string|max:255',
            'data.attributes.privilege' => 'integer',
            'data.attributes.password' => 'nullable|string',
            'data.attributes.cardNumber' => 'nullable|string|unique:attendees,card_number',
            'data.attributes.viceCard' => 'nullable|string',
            'data.attributes.groupId' => 'integer',
            'data.attributes.timezone' => 'nullable|string',
            'data.attributes.verificationMode' => 'integer',
            'data.attributes.siteIds' => 'nullable|array',
            'data.attributes.siteIds.*' => 'uuid|exists:sites,id',
            'data.attributes.deviceSerials' => 'nullable|array',
            'data.attributes.deviceSerials.*' => 'string|exists:devices,serial_number',
        ]);

        $attrs = $validated['data']['attributes'];

        $attendee = Attendee::create([
            'pin' => $attrs['pin'],
            'name' => $attrs['name'] ?? null,
            'privilege' => $attrs['privilege'] ?? 0,
            'password' => $attrs['password'] ?? null,
            'card_number' => $attrs['cardNumber'] ?? null,
            'vice_card' => $attrs['viceCard'] ?? null,
            'group_id' => $attrs['groupId'] ?? 1,
            'timezone' => $attrs['timezone'] ?? null,
            'verification_mode' => $attrs['verificationMode'] ?? -1,
        ]);

        if (!empty($attrs['siteIds'])) {
            foreach ($attrs['siteIds'] as $siteId) {
                if ($request->user()->hasAccessToSite($siteId)) {
                    $attendee->sites()->syncWithoutDetaching([
                        $siteId => ['is_active' => true, 'granted_at' => now()],
                    ]);
                }
            }
        }

        $deviceSerials = $attrs['deviceSerials'] ?? [];
        foreach ($deviceSerials as $serial) {
            $attendee->grantAccessToDevice($serial);
        }

        event(new AttendeeCreated($attendee, $deviceSerials));

        return new AttendeeResource($attendee->load(['sites', 'devices']));
    }

    #[Authorize('view', 'attendee')]
    public function show(Attendee $attendee): AttendeeResource
    {
        return new AttendeeResource($attendee);
    }

    #[Authorize('update', 'attendee')]
    public function update(Request $request, Attendee $attendee): AttendeeResource
    {
        $validated = $request->validate([
            'data.attributes.name' => 'sometimes|string|max:255',
            'data.attributes.privilege' => 'integer',
            'data.attributes.password' => 'nullable|string',
            'data.attributes.cardNumber' => 'nullable|string|unique:attendees,card_number,' . $attendee->id,
            'data.attributes.viceCard' => 'nullable|string',
            'data.attributes.groupId' => 'integer',
            'data.attributes.timezone' => 'nullable|string',
            'data.attributes.verificationMode' => 'integer',
        ]);

        $attrs = $validated['data']['attributes'] ?? [];

        $attendee->update(array_filter([
            'name' => $attrs['name'] ?? null,
            'privilege' => $attrs['privilege'] ?? null,
            'password' => $attrs['password'] ?? null,
            'card_number' => $attrs['cardNumber'] ?? null,
            'vice_card' => $attrs['viceCard'] ?? null,
            'group_id' => $attrs['groupId'] ?? null,
            'timezone' => $attrs['timezone'] ?? null,
            'verification_mode' => $attrs['verificationMode'] ?? null,
        ], fn($v) => $v !== null));

        $attendee = $attendee->fresh();
        event(new AttendeeUpdated($attendee));

        return new AttendeeResource($attendee);
    }

    #[Authorize('delete', 'attendee')]
    public function destroy(Attendee $attendee)
    {
        $pin = $attendee->pin;
        $serials = $attendee->devices()->pluck('serial_number')->all();

        $attendee->delete();

        event(new AttendeeDeleted($pin, $serials));

        return response()->json(null, 204);
    }

    #[Authorize('manageAccess', 'attendee')]
    public function grantDeviceAccess(Request $request, Attendee $attendee)
    {
        $validated = $request->validate([
            'data.attributes.deviceSerial' => 'required|string|exists:devices,serial_number',
            'data.attributes.privilege' => 'nullable|integer',
            'data.attributes.groupId' => 'nullable|integer',
            'data.attributes.timezone' => 'nullable|string',
        ]);

        $attrs = $validated['data']['attributes'];

        $attendee->grantAccessToDevice(
            $attrs['deviceSerial'],
            array_filter([
                'privilege' => $attrs['privilege'] ?? null,
                'group_id' => $attrs['groupId'] ?? null,
                'timezone' => $attrs['timezone'] ?? null,
            ])
        );

        event(new AttendeeAccessGranted($attendee, $attrs['deviceSerial']));

        return new AttendeeResource($attendee->load('devices'));
    }

    #[Authorize('manageAccess', 'attendee')]
    public function revokeDeviceAccess(Request $request, Attendee $attendee)
    {
        $validated = $request->validate([
            'data.attributes.deviceSerial' => 'required|string|exists:devices,serial_number',
        ]);

        $serial = $validated['data']['attributes']['deviceSerial'];
        $attendee->revokeAccessFromDevice($serial);

        event(new AttendeeAccessRevoked($attendee, $serial));

        return new AttendeeResource($attendee->load('devices'));
    }

    /**
     * POST /api/v1/attendees/bulk
     *
     * Upsert many attendees by PIN. Partial success returns 207.
     */
    #[Authorize('create', Attendee::class)]
    public function bulkStore(BulkUpsertAttendeesRequest $request, BulkAttendeeService $bulk): JsonResponse
    {
        $attrs = $request->validated()['data']['attributes'];
        $result = $bulk->upsert(
            $request->user(),
            $attrs['items'],
            $attrs['mode'] ?? 'upsert',
        );

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    /**
     * POST /api/v1/attendees/bulk-access
     *
     * Grant or revoke many PINs on many devices in one call.
     */
    #[Authorize('manageAccessAny', Attendee::class)]
    public function bulkAccess(BulkAttendeeAccessRequest $request, BulkAttendeeService $bulk): JsonResponse
    {
        $attrs = $request->validated()['data']['attributes'];

        $overrides = array_filter([
            'privilege' => $attrs['privilege'] ?? null,
            'group_id' => $attrs['groupId'] ?? null,
            'timezone' => $attrs['timezone'] ?? null,
        ], fn ($v) => $v !== null);

        $result = $bulk->syncAccess(
            $request->user(),
            $attrs['pins'],
            $attrs['deviceSerials'],
            $attrs['action'],
            $overrides,
        );

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    /**
     * GET /api/v1/attendees/import-template?format=xlsx|csv
     */
    #[Authorize('create', Attendee::class)]
    public function importTemplate(Request $request, SpreadsheetImportService $import)
    {
        $format = $request->input('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        $file = $import->downloadTemplate('attendees', $format);

        return response($file['body'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }

    /**
     * POST /api/v1/attendees/import  (multipart: file, mode, dryRun, siteIds, deviceSerials)
     */
    #[Authorize('create', Attendee::class)]
    public function import(ImportSpreadsheetRequest $request, SpreadsheetImportService $import): JsonResponse
    {
        try {
            $result = $import->importAttendees($request->user(), $request->file('file'), [
                'mode' => $request->input('mode', 'upsert'),
                'dryRun' => $request->boolean('dryRun'),
                'siteIds' => $this->explodeFormList($request->input('siteIds')),
                'deviceSerials' => $this->explodeFormList($request->input('deviceSerials')),
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

    /**
     * @return list<string>
     */
    private function explodeFormList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $raw) ?: [])));
    }
}
