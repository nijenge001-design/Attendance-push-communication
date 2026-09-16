<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v1\BulkDeviceAssignRequest;
use App\Http\Resources\Api\v1\AttendeeResource;
use App\Http\Resources\Api\v1\BulkResultResource;
use App\Http\Resources\Api\v1\DeviceResource;
use App\Http\Resources\Api\v1\PendingCommandResource;
use App\Models\Attendee;
use App\Models\Device;
use App\Models\Site;
use App\Services\BulkDeviceAssignService;
use App\Services\CommandBuilder;
use App\Services\DeviceCommandQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class DeviceController extends Controller
{
    #[Authorize('viewAny', Device::class)]
    public function index(Request $request)
    {
        $query = Device::query()
            ->accessibleBy($request->user());

        $siteIds = $request->user()->accessibleSiteIds();
        if ($siteIds !== null) {
            $query->whereIn('site_id', $siteIds);
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('filter.online')) {
            $query->online();
        }

        if ($request->boolean('filter.offline')) {
            $query->offline();
        }

        if ($siteId = $request->input('filter.site')) {
            $query->where('site_id', $siteId);
        }

        if ($search = $request->input('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        $devices = $query->latest('last_seen_at')->paginate($request->input('page.size', 15));

        return DeviceResource::collection($devices);
    }

    /**
     * Route: POST /sites/{site}/devices
     *
     * Array syntax: ['site', Device::class]
     *   - 'site'         → resolves the bound Site model as the first argument
     *   - Device::class  → tells Laravel the policy target class (needed because
     *                      the first element is a model, not a class)
     */

    #[Authorize('create', ['site', Device::class])]
    public function store(Request $request, Site $site): DeviceResource
    {
        $validated = $request->validate([
            'data.attributes.serialNumber' => 'required|string|unique:devices,serial_number',
            'data.attributes.deviceName' => 'nullable|string|max:255',
            'data.attributes.status' => 'sometimes|in:pending,approved,blocked',
        ]);

        $attrs = $validated['data']['attributes'];

        $device = Device::create([
            'serial_number' => $attrs['serialNumber'],
            'device_name' => $attrs['deviceName'] ?? null,
            'site_id' => $site->id,
            'status' => $attrs['status'] ?? Device::STATUS_PENDING,
        ]);

        return new DeviceResource($device);
    }

    /**
     * Route: GET /devices/{device}
     *
     * String syntax is sufficient — only the Device is needed.
     * 'device' resolves to the bound Device model.
     */

    #[Authorize('view', 'device')]
    public function show(Device $device): DeviceResource
    {
        return new DeviceResource($device);
    }

    /**
     * Route: PUT /sites/{site}/devices/{device}
     *
     * Array syntax: ['device', 'site']
     *   - First element is the route param whose model is passed first
     *   - Subsequent elements are additional args passed positionally
     *
     * NOTE: The policy must accept `Site` (not string) as its third arg.
     */

    #[Authorize('update', ['device', 'site'])]
    public function update(Request $request, Site $site, Device $device): DeviceResource
    {
        $validated = $request->validate([
            'data.attributes.deviceName' => 'sometimes|string|max:255',
            'data.attributes.status' => 'sometimes|in:pending,approved,blocked',
            'data.attributes.rejectionReason' => 'nullable|string',
        ]);

        $attrs = $validated['data']['attributes'] ?? [];

        $data = array_filter([
            'device_name' => $attrs['deviceName'] ?? null,
            'status' => $attrs['status'] ?? null,
            'rejection_reason' => $attrs['rejectionReason'] ?? null,
        ], fn ($v) => $v !== null);

        // Route status changes through model methods so domain events fire once.
        if (isset($data['status'])) {
            if ($data['status'] === Device::STATUS_APPROVED) {
                $device->approve();
                unset($data['status'], $data['rejection_reason']);
            } elseif ($data['status'] === Device::STATUS_BLOCKED) {
                $device->block($data['rejection_reason'] ?? null);
                unset($data['status'], $data['rejection_reason']);
            }
        }

        if ($data !== []) {
            $device->update($data);
        }

        if ($device->site_id !== $site->id) {
            $device->site_id = $site->id;
            $device->save();
        }

        return new DeviceResource($device->fresh());
    }

    /**
     * POST /api/v1/sites/{site}/devices/{device}/assign
     */
    #[Authorize('assign', ['device', 'site'])]
    public function assign(Site $site, Device $device): DeviceResource
    {
        return $this->applyAssign($device, $site);
    }

    /**
     * POST /api/v1/sites/{site}/devices/bulk-assign
     *
     * Assign many existing devices to this site.
     *
     * Authorize: Device class FIRST so DevicePolicy is used (admin bypass
     * lives there). Then the bound {site} is passed into assignAny.
     */
    #[Authorize('assignAny', [Device::class, 'site'])]
    public function bulkAssignToSite(
        BulkDeviceAssignRequest $request,
        Site $site,
        BulkDeviceAssignService $bulk,
    ): JsonResponse {
        $serials = $request->validated()['data']['attributes']['serialNumbers'];
        $result = $bulk->assignToSite($request->user(), $site, $serials);

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    /**
     * POST /api/v1/devices/bulk-assign
     *
     * Either:
     *   { siteId, serialNumbers: [...] }
     *   { items: [{ serialNumber, siteId }, ...] }  — mixed sites
     */
    #[Authorize('assignAny', Device::class)]
    public function bulkAssign(
        BulkDeviceAssignRequest $request,
        BulkDeviceAssignService $bulk,
    ): JsonResponse {
        $attrs = $request->validated()['data']['attributes'];

        if (isset($attrs['items'])) {
            $result = $bulk->assignItems($request->user(), $attrs['items']);
        } else {
            $site = Site::query()->findOrFail($attrs['siteId']);
            $this->authorize('assignAny', [Device::class, $site]);
            $result = $bulk->assignToSite($request->user(), $site, $attrs['serialNumbers']);
        }

        return (new BulkResultResource($result))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    /**
     * POST /api/v1/devices/{device}/assign-site
     *
     * Body: { "data": { "attributes": { "siteId": "<uuid>" } } }
     */
    public function assignSite(Request $request, Device $device): DeviceResource
    {
        $validated = $request->validate([
            'data.attributes.siteId' => 'required|uuid|exists:sites,id',
        ]);

        $site = Site::query()->findOrFail($validated['data']['attributes']['siteId']);
        $this->authorize('assign', [$device, $site]);

        return $this->applyAssign($device, $site);
    }

    private function applyAssign(Device $device, Site $site): DeviceResource
    {
        if (! $site->is_active) {
            abort(422, 'Cannot assign a device to an inactive site.');
        }

        $device->site_id = $site->id;
        $device->save();

        return new DeviceResource($device->fresh()->load('site'));
    }

    #[Authorize('delete', 'device')]
    public function destroy(Device $device)
    {
        $device->delete();

        return response()->json(null, 204);
    }

    #[Authorize('approve', 'device')]
    public function approve(Device $device): DeviceResource
    {
        $device->approve();

        return new DeviceResource($device->fresh());
    }

    #[Authorize('block', 'device')]
    public function block(Request $request, Device $device): DeviceResource
    {
        $reason = $request->input('data.attributes.rejectionReason');

        $device->block($reason);

        return new DeviceResource($device->fresh());
    }

    /**
     * GET /api/v1/devices/{device}/attendees
     *
     * Attendees already linked to this device (from USERINFO / OPERLOG / query).
     */
    #[Authorize('view', 'device')]
    public function attendees(Request $request, Device $device)
    {
        $query = Attendee::query()
            ->accessibleBy($request->user())
            ->onDevice($device->serial_number);

        if ($pin = $request->input('filter.pin')) {
            $query->where('pin', $pin);
        }

        if ($search = $request->input('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('pin', 'like', "%{$search}%")
                    ->orWhere('card_number', 'like', "%{$search}%");
            });
        }

        $pageSize = min(100, max(1, (int) $request->input('page.size', 15)));

        return AttendeeResource::collection($query->latest()->paginate($pageSize));
    }

    /**
     * POST /api/v1/devices/{device}/sync-users
     *
     * Queue DATA QUERY USERINFO. Device will POST users on next poll;
     * CommandResponseHandler imports them.
     *
     * Optional pin in data.attributes.pin — omit to pull every user.
     */
    #[Authorize('syncUsers', 'device')]
    public function syncUsers(Request $request, Device $device, DeviceCommandQueue $queue): PendingCommandResource
    {
        if ($device->status !== Device::STATUS_APPROVED) {
            abort(422, 'Device must be approved before pulling users.');
        }

        $validated = $request->validate([
            'data.attributes.pin' => 'nullable|string|max:32',
        ]);

        $pin = $validated['data']['attributes']['pin'] ?? null;
        $command = CommandBuilder::queryUserInfo($pin);

        $pending = $queue->queue($device->serial_number, $command);

        return new PendingCommandResource($pending);
    }
}
