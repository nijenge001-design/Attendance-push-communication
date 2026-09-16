<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\PendingCommandResource;
use App\Models\Device;
use App\Models\PendingCommand;
use App\Services\DeviceCommandQueue;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class PendingCommandController extends Controller
{
    #[Authorize('viewAny', PendingCommand::class)]
    public function index(Request $request)
    {
        $query = PendingCommand::query();

        $siteIds = $request->user()->accessibleSiteIds();
        if ($siteIds !== null) {
            $serials = Device::whereIn('site_id', $siteIds)->pluck('serial_number');
            $query->whereIn('device_serial', $serials);
        }

        if ($device = $request->input('filter.device')) {
            $query->forDevice($device);
        }

        if ($request->boolean('filter.pending')) {
            $query->pending();
        }

        if ($request->boolean('filter.executed')) {
            $query->executed();
        }

        if ($request->boolean('filter.ready')) {
            $query->readyToSend();
        }

        if ($request->boolean('filter.stuck')) {
            $query->stuck();
        }

        $commands = $query->latest()->paginate($request->input('page.size', 20));

        return PendingCommandResource::collection($commands);
    }

    /**
     * Route: POST /devices/{device}/commands
     *
     * Array syntax: ['device', PendingCommand::class]
     *   - 'device'              → route-bound Device passed as first arg
     *   - PendingCommand::class → policy target class
     * @throws DateMalformedStringException
     */
    #[Authorize('create', ['device', PendingCommand::class])]
    public function store(Request $request, Device $device, DeviceCommandQueue $queue): PendingCommandResource
    {
        $validated = $request->validate([
            'data.attributes.commandText' => 'required|string',
            'data.attributes.scheduledAt' => 'nullable|date',
            'data.attributes.recurrence' => 'nullable|string',
        ]);

        $attrs = $validated['data']['attributes'];

        $command = $queue->queue(
            $device->serial_number,
            $attrs['commandText'],
            isset($attrs['scheduledAt']) ? new DateTimeImmutable($attrs['scheduledAt']) : null,
            $attrs['recurrence'] ?? null
        );

        return new PendingCommandResource($command);
    }

    #[Authorize('view', 'pendingCommand')]
    public function show(PendingCommand $pendingCommand): PendingCommandResource
    {
        return new PendingCommandResource($pendingCommand);
    }

    #[Authorize('update', 'pendingCommand')]
    public function update(Request $request, PendingCommand $pendingCommand): PendingCommandResource
    {
        $validated = $request->validate([
            'data.attributes.enabled' => 'boolean',
            'data.attributes.scheduledAt' => 'nullable|date',
            'data.attributes.recurrence' => 'nullable|string',
            'data.attributes.maxRetries' => 'integer|min:0|max:10',
        ]);

        $attrs = $validated['data']['attributes'] ?? [];

        $data = array_filter([
            'enabled' => $attrs['enabled'] ?? null,
            'scheduled_at' => $attrs['scheduledAt'] ?? null,
            'recurrence' => $attrs['recurrence'] ?? null,
            'max_retries' => $attrs['maxRetries'] ?? null,
        ], fn($v) => $v !== null);

        if (array_key_exists('enabled', $data)) {
            $data['enabled_at'] = $data['enabled'] ? now() : null;
        }

        $pendingCommand->update($data);

        return new PendingCommandResource($pendingCommand->fresh());
    }

    #[Authorize('delete', 'pendingCommand')]
    public function destroy(PendingCommand $pendingCommand)
    {
        $pendingCommand->delete();

        return response()->json(null, 204);
    }

    #[Authorize('enable', 'pendingCommand')]
    public function enable(PendingCommand $pendingCommand): PendingCommandResource
    {
        $pendingCommand->enable();

        return new PendingCommandResource($pendingCommand->fresh());
    }

    #[Authorize('disable', 'pendingCommand')]
    public function disable(PendingCommand $pendingCommand): PendingCommandResource
    {
        $pendingCommand->disable();

        return new PendingCommandResource($pendingCommand->fresh());
    }
}
