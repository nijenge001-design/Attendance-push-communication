<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class SiteResource extends JsonApiResource
{
    public function type(): string
    {
        return 'sites';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'isActive' => $this->is_active,
            'meta' => $this->meta,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'devices' => DeviceResource::class,
            'attendees' => AttendeeResource::class,
            'users' => UserResource::class,
        ];
    }
}
