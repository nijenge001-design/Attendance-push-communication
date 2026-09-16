<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\PhotoResource;
use App\Models\Device;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class PhotoController extends Controller
{
    #[Authorize('viewAny', Photo::class)]
    public function index(Request $request)
    {
        $query = Photo::query()
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

        if ($request->boolean('filter.attendance')) {
            $query->attendance();
        }

        if ($request->boolean('filter.face')) {
            $query->face();
        }

        $photos = $query->latest()->paginate($request->input('page.size', 30));

        return PhotoResource::collection($photos);
    }

    #[Authorize('view', 'photo')]
    public function show(Photo $photo): PhotoResource
    {
        return new PhotoResource($photo);
    }

    #[Authorize('delete', 'photo')]
    public function destroy(Photo $photo)
    {
        $photo->delete();

        return response()->json(null, 204);
    }
}
