<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\UserPermissionResource;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\Rule;

#[Authorize('managePermissions', 'user')]
class UserPermissionController extends Controller
{
    /**
     * List effective overrides for a user (not role defaults).
     */
    public function index(Request $request, User $user)
    {
        $query = UserPermission::where('user_id', $user->id);

        if ($request->has('filter.granted')) {
            $query->where('granted', $request->boolean('filter.granted'));
        }

        $items = $query->orderBy('permission')->paginate($request->input('page.size', 50));

        return UserPermissionResource::collection($items);
    }

    /**
     * Grant or deny a permission override.
     *
     * Body:
     * {
     *   "data": {
     *     "attributes": {
     *       "permission": "manage-commands",
     *       "granted": true
     *     }
     *   }
     * }
     */
    public function store(Request $request, User $user): UserPermissionResource
    {
        $validated = $request->validate([
            'data.attributes.permission' => ['required', Rule::enum(Permission::class)],
            'data.attributes.granted' => 'required|boolean',
        ]);

        $attrs = $validated['data']['attributes'];
        $permission = $attrs['permission'];
        $granted = (bool)$attrs['granted'];

        if ($granted) {
            $user->grantPermission($permission);
        } else {
            $user->denyPermission($permission);
        }

        $row = UserPermission::where('user_id', $user->id)
            ->where('permission', $permission instanceof Permission ? $permission->value : $permission)
            ->firstOrFail();

        return new UserPermissionResource($row);
    }

    /**
     * Update an existing override (flip grant/deny).
     */
    public function update(Request $request, User $user, UserPermission $userPermission): UserPermissionResource
    {
        if ($userPermission->user_id !== $user->id) {
            abort(404);
        }

        $validated = $request->validate([
            'data.attributes.granted' => 'required|boolean',
        ]);

        $granted = (bool)$validated['data']['attributes']['granted'];

        if ($granted) {
            $user->grantPermission($userPermission->permission);
        } else {
            $user->denyPermission($userPermission->permission);
        }

        return new UserPermissionResource($userPermission->fresh());
    }

    /**
     * Remove override → fall back to role default.
     */
    public function destroy(User $user, UserPermission $userPermission)
    {
        if ($userPermission->user_id !== $user->id) {
            abort(404);
        }

        $perm = $userPermission->permission;
        $user->clearPermissionOverride($perm);

        return response()->json(null, 204);
    }

    /**
     * List all permission names with effective value for this user
     * (role default merged with overrides).
     */
    public function effective(Request $request, User $user)
    {
        $data = [];
        foreach (Permission::cases() as $permission) {
            $data[] = [
                'type' => 'effective-permissions',
                'id' => $permission->value,
                'attributes' => [
                    'permission' => $permission->value,
                    'permissionLabel' => $permission->label(),
                    'granted' => $user->hasPermission($permission),
                    'source' => $this->source($user, $permission),
                ],
            ];
        }

        return response()->json(['data' => $data])
            ->header('Content-Type', 'application/vnd.api+json');
    }

    protected function source(User $user, Permission $permission): string
    {
        if ($user->isAdmin()) {
            return 'admin';
        }

        $overrides = UserPermission::where('user_id', $user->id)
            ->where('permission', $permission->value)
            ->first();

        if ($overrides) {
            return $overrides->granted ? 'override-grant' : 'override-deny';
        }

        return 'role';
    }
}
