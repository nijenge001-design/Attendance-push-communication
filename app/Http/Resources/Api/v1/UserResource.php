<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    public function type(): string
    {
        return 'users';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phoneNumber' => $this->phone_number,
            'role' => $this->role?->value,
            'roleLabel' => $this->role?->label(),
            'phoneVerifiedAt' => $this->phone_verified_at?->toIso8601String(),
            'emailVerifiedAt' => $this->email_verified_at?->toIso8601String(),
            'passwordChangedAt' => $this->password_changed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'sites' => SiteResource::class,
        ];
    }
}
