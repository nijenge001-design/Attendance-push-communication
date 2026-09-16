<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserPermissionResource extends JsonApiResource
{
    public function type(): string
    {
        return 'user-permissions';
    }

    public function toAttributes(Request $request): array
    {
        $permission = $this->permission;

        return [
            'permission' => $permission instanceof \BackedEnum ? $permission->value : $permission,
            'permissionLabel' => $permission instanceof \App\Enums\Permission
                ? $permission->label()
                : null,
            'granted' => (bool) $this->granted,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'user' => UserResource::class,
        ];
    }
}
