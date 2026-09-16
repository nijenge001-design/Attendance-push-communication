<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\BioTemplateResource;
use App\Models\BioTemplate;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class BioTemplateController extends Controller
{
    #[Authorize('viewAny', BioTemplate::class)]
    public function index(Request $request)
    {
        $query = BioTemplate::query()
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

        if ($type = $request->input('filter.type')) {
            $query->ofType((int)$type);
        }

        if ($request->boolean('filter.valid')) {
            $query->valid();
        }

        if ($request->boolean('filter.finger')) {
            $query->finger();
        }

        if ($request->boolean('filter.face')) {
            $query->face();
        }

        $templates = $query->latest()->paginate($request->input('page.size', 30));

        return BioTemplateResource::collection($templates);
    }

    #[Authorize('view', 'bioTemplate')]
    public function show(BioTemplate $bioTemplate): BioTemplateResource
    {
        return new BioTemplateResource($bioTemplate);
    }

    #[Authorize('delete', 'bioTemplate')]
    public function destroy(BioTemplate $bioTemplate)
    {
        $bioTemplate->delete();

        return response()->json(null, 204);
    }
}
