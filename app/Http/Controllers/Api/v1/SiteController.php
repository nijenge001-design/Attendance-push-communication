<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\SiteResource;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class SiteController extends Controller
{
    #[Authorize('viewAny', Site::class)]
    public function index(Request $request)
    {
        $query = Site::query()->accessibleBy($request->user());

        if ($request->boolean('filter.active')) {
            $query->active();
        }

        if ($search = $request->input('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $sites = $query->latest()->paginate($request->input('page.size', 15));

        return SiteResource::collection($sites);
    }

    #[Authorize('create', Site::class)]
    public function store(Request $request): SiteResource
    {
        $validated = $request->validate([
            'data.attributes.code' => 'required|string|max:64|unique:sites,code',
            'data.attributes.name' => 'required|string|max:255',
            'data.attributes.timezone' => 'nullable|string|max:64',
            'data.attributes.address' => 'nullable|string|max:255',
            'data.attributes.city' => 'nullable|string|max:100',
            'data.attributes.country' => 'nullable|string|max:100',
            'data.attributes.isActive' => 'boolean',
            'data.attributes.meta' => 'nullable|array',
        ]);

        $attrs = $validated['data']['attributes'];

        $site = Site::create([
            'code' => $attrs['code'],
            'name' => $attrs['name'],
            'timezone' => $attrs['timezone'] ?? null,
            'address' => $attrs['address'] ?? null,
            'city' => $attrs['city'] ?? null,
            'country' => $attrs['country'] ?? null,
            'is_active' => $attrs['isActive'] ?? true,
            'meta' => $attrs['meta'] ?? null,
        ]);

        // Assign creator to the new site (non-admins)
        if (!$request->user()->isAdmin()) {
            $request->user()->sites()->syncWithoutDetaching([
                $site->id => ['is_active' => true],
            ]);
        }

        return new SiteResource($site);
    }

    #[Authorize('view', 'site')]
    public function show(Request $request, Site $site): SiteResource
    {
        return new SiteResource($site);
    }

    #[Authorize('update', 'site')]
    public function update(Request $request, Site $site): SiteResource
    {
        $validated = $request->validate([
            'data.attributes.code' => 'sometimes|string|max:64|unique:sites,code,' . $site->id,
            'data.attributes.name' => 'sometimes|string|max:255',
            'data.attributes.timezone' => 'nullable|string|max:64',
            'data.attributes.address' => 'nullable|string|max:255',
            'data.attributes.city' => 'nullable|string|max:100',
            'data.attributes.country' => 'nullable|string|max:100',
            'data.attributes.isActive' => 'boolean',
            'data.attributes.meta' => 'nullable|array',
        ]);

        $attrs = $validated['data']['attributes'] ?? [];

        $site->update(array_filter([
            'code' => $attrs['code'] ?? null,
            'name' => $attrs['name'] ?? null,
            'timezone' => $attrs['timezone'] ?? null,
            'address' => $attrs['address'] ?? null,
            'city' => $attrs['city'] ?? null,
            'country' => $attrs['country'] ?? null,
            'is_active' => $attrs['isActive'] ?? null,
            'meta' => $attrs['meta'] ?? null,
        ], fn($v) => $v !== null));

        return new SiteResource($site->fresh());
    }

    #[Authorize('delete', 'site')]
    public function destroy(Site $site)
    {
        $site->delete();

        return response()->json(null, 204);
    }
}
